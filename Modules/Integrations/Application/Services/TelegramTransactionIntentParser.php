<?php

namespace Modules\Integrations\Application\Services;

use App\Core\Enums\TransactionType;

class TelegramTransactionIntentParser
{
    /**
     * @return array{type: TransactionType, amount: float, description: string}|null
     */
    public function parse(string $text): ?array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return null;
        }

        $type = $this->detectType($text);
        if (! $type) {
            return null;
        }

        $amount = $this->extractAmount($text);
        if ($amount === null || $amount <= 0) {
            return null;
        }

        $description = $this->extractDescription($text, $amount) ?: $text;

        return [
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
        ];
    }

    protected function detectType(string $text): ?TransactionType
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b(gasto|gastos|despesa|despesas|paguei|pagar|pago|compra|comprei|investi|investimento|aportando|aporte)\b/u', $lower)) {
            return TransactionType::Expense;
        }

        if (preg_match('/\b(receita|recebi|recebimento|recebido|ganho|ganhei|entrada|vendi|venda|faturamento)\b/u', $lower)) {
            return TransactionType::Income;
        }

        return null;
    }

    protected function extractAmount(string $text): ?float
    {
        if (! preg_match(
            '/(?:r\$\s*)?(\d{1,3}(?:\.\d{3})+(?:,\d{2})?|\d+(?:,\d{2})?)(?!\d)/u',
            $text,
            $matches
        )) {
            return null;
        }

        return $this->parseBrazilianAmount($matches[1]);
    }

    protected function parseBrazilianAmount(string $raw): float
    {
        $raw = trim($raw);

        if (str_contains($raw, ',')) {
            [$intPart, $decPart] = array_pad(explode(',', $raw, 2), 2, '00');
            $intPart = str_replace('.', '', $intPart);

            return (float) ($intPart.'.'.str_pad($decPart, 2, '0'));
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw)) {
            return (float) str_replace('.', '', $raw);
        }

        return (float) $raw;
    }

    protected function extractDescription(string $text, float $amount): string
    {
        $patterns = [
            '/\b(?:adicionar|adicione|registrar|registre|lançar|lancar|incluir|criar)\b[^.]*?\b(?:gasto|despesa|receita)\b[^.]*?\d[\d.,]*/iu',
            '/\b(?:gasto|despesa|receita)\s+(?:de\s+)?\d[\d.,]*/iu',
            '/\b(?:r\$\s*)?\d[\d.,]+/iu',
        ];

        $remainder = $text;
        foreach ($patterns as $pattern) {
            $remainder = preg_replace($pattern, '', $remainder, 1) ?? $remainder;
        }

        $remainder = trim(preg_replace('/\s+/u', ' ', $remainder) ?? $remainder, " \t\n\r\0\x0B.,;:-");

        if (mb_strlen($remainder) >= 3) {
            return mb_convert_case($remainder, MB_CASE_TITLE, 'UTF-8');
        }

        return 'Lançamento via Telegram';
    }
}
