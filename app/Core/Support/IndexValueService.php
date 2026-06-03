<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Finance\Infrastructure\Models\RecurringItem;

/**
 * Busca o valor corrente de índices financeiros usados em receitas recorrentes.
 *
 * Índices suportados:
 *   salario_minimo  — Salário Mínimo brasileiro (BrasilAPI, cache 24h)
 *   usdt            — Cotação USDT/BRL (Binance, cache 5min)
 *
 * amount_factor semântica:
 *   salario_minimo  → factor é a fração decimal (1,75% = 0.0175)
 *   usdt            → factor é a quantidade de USDT (100 USDT = 100)
 */
class IndexValueService
{
    public const SALARIO_MINIMO = 'salario_minimo';
    public const USDT           = 'usdt';

    /** Salário mínimo de referência — atualizado anualmente pela lei. */
    private const SALARIO_MINIMO_FALLBACK = 1518.00; // 2025

    /** Índices disponíveis para o selector na UI. */
    public const AVAILABLE = [
        self::SALARIO_MINIMO => 'Salário Mínimo',
        self::USDT           => 'USDT',
    ];

    public function __construct(
        private readonly CurrencyExchangeService $exchange,
    ) {}

    /**
     * Retorna valor e metadados do índice.
     *
     * @return array{value: float, label: string, source: string}
     */
    public function fetchValue(string $index): array
    {
        return match ($index) {
            self::SALARIO_MINIMO => $this->fetchSalarioMinimo(),
            self::USDT           => $this->fetchUsdt(),
            default              => throw new \InvalidArgumentException("Índice desconhecido: {$index}"),
        };
    }

    /**
     * Calcula o valor em BRL baseado no índice e fator do RecurringItem.
     *
     * @return array{amount: float, index_value: float, index_label: string, formula: string}|null
     */
    public function calculate(RecurringItem $item): ?array
    {
        if (! $item->amount_index || $item->amount_factor === null) {
            return null;
        }

        $index  = $this->fetchValue($item->amount_index);
        $factor = (float) $item->amount_factor;

        $amount = round($index['value'] * $factor, 2);

        $formula = $this->formulaLabel($item->amount_index, $factor, $index['value'], $amount);

        return [
            'amount'      => $amount,
            'index_value' => $index['value'],
            'index_label' => $index['label'],
            'formula'     => $formula,
            'source'      => $index['source'],
        ];
    }

    /** Rótulo legível da fórmula: "1,75% × R$ 1.518,00 (Salário Mínimo) = R$ 26,57" */
    public function formulaLabel(string $index, float $factor, float $indexValue, float $result): string
    {
        $fmtIndex  = 'R$ '.number_format($indexValue, 2, ',', '.');
        $fmtResult = 'R$ '.number_format($result, 2, ',', '.');

        return match ($index) {
            self::SALARIO_MINIMO => number_format($factor * 100, 4, ',', '.').'% × '.$fmtIndex.' (Salário Mínimo) = '.$fmtResult,
            self::USDT           => number_format($factor, 2, ',', '.').' USDT × '.$fmtIndex.' = '.$fmtResult,
            default              => $fmtResult,
        };
    }

    /** Rótulo curto para exibição no form (ex.: "1,75% do Salário Mínimo", "100 USDT"). */
    public function shortFormulaLabel(string $index, float $factor): string
    {
        return match ($index) {
            self::SALARIO_MINIMO => number_format($factor * 100, 4, ',', '.').'% do Salário Mínimo',
            self::USDT           => number_format($factor, 2, ',', '.').' USDT',
            default              => (string) $factor,
        };
    }

    // ── Fontes ────────────────────────────────────────────────────────────────

    /** @return array{value: float, label: string, source: string} */
    private function fetchSalarioMinimo(): array
    {
        $cached = Cache::remember('index_salario_minimo', 3600 * 24, function () {
            try {
                $response = Http::timeout(5)->get('https://brasilapi.com.br/api/salario-minimo/v1');
                $data = $response->json();

                if (is_array($data) && ! empty($data)) {
                    $latest = collect($data)->sortByDesc('ano')->first();
                    $valor  = (float) ($latest['valor'] ?? 0);

                    if ($valor > 0) {
                        return ['value' => $valor, 'source' => 'brasilapi'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('IndexValueService: falha ao buscar salário mínimo', ['error' => $e->getMessage()]);
            }

            return ['value' => self::SALARIO_MINIMO_FALLBACK, 'source' => 'fallback'];
        });

        return array_merge($cached, ['label' => 'Salário Mínimo']);
    }

    /** @return array{value: float, label: string, source: string} */
    private function fetchUsdt(): array
    {
        return [
            'value'  => $this->exchange->usdtToBrl(),
            'label'  => 'USDT',
            'source' => 'binance',
        ];
    }
}
