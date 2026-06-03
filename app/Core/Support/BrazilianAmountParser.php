<?php

namespace App\Core\Support;

/**
 * Interpreta valores monetários no padrão brasileiro (R$ 5.000,00) e evita
 * capturar só os primeiros dígitos de "5000.00" como 500.
 */
class BrazilianAmountParser
{
    /**
     * Converte um valor isolado (string ou número) para float.
     */
    public function parse(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value > 0 ? round((float) $value, 2) : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/^R\$\s*/iu', '', $raw) ?? $raw;

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $raw)) {
            $normalized = str_replace('.', '', $raw);
            $normalized = str_replace(',', '.', $normalized);

            return round((float) $normalized, 2);
        }

        if (preg_match('/^\d+(,\d{1,2})$/', $raw)) {
            return round((float) str_replace(',', '.', $raw), 2);
        }

        if (preg_match('/^\d+\.\d{2}$/', $raw)) {
            return round((float) $raw, 2);
        }

        if (preg_match('/^\d+$/', $raw)) {
            return round((float) $raw, 2);
        }

        return $this->extractBestFromText($raw);
    }

    /**
     * Dica a partir do nome do arquivo (ex.: "Rodolfo 5k.jpeg" → 5000).
     */
    public function hintFromFilename(string $filename): ?float
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*k\b/iu', $base, $m)) {
            $n = (float) str_replace(',', '.', $m[1]);

            return round($n * 1000, 2);
        }

        if (preg_match('/(\d{1,3}(?:\.\d{3})+(?:,\d{2})?|\d+(?:,\d{2})?)\s*(?:reais|rs)?\b/iu', $base, $m)) {
            return $this->parse($m[1]);
        }

        return null;
    }

    /**
     * Extrai o valor total em USD/USDT de um texto OCR.
     * Prefere o valor associado a "Total", senão retorna o maior encontrado.
     */
    public function extractUsdAmount(string $text): ?float
    {
        $pattern = '/(?:total|sub\s*total|amount)[\s:]*\$?\s*(\d+(?:\.\d{1,2})?)\s*(?:USD|USDT)?/iu';
        if (preg_match_all($pattern, $text, $matches)) {
            $values = array_map('floatval', $matches[1]);
            return round(max($values), 2);
        }

        // Fallback: any USD-marked value
        if (preg_match_all('/\$\s*(\d+(?:\.\d{1,2})?)\s*(?:USD|USDT)/iu', $text, $matches)) {
            $values = array_map('floatval', $matches[1]);
            return round(max($values), 2);
        }

        if (preg_match_all('/(\d+(?:\.\d{1,2})?)\s*USD\b/iu', $text, $matches)) {
            $values = array_map('floatval', $matches[1]);
            return round(max($values), 2);
        }

        return null;
    }

    /**
     * Escolhe o melhor valor monetário no texto completo do OCR.
     */
    public function extractBestFromText(string $text, ?float $filenameHint = null): ?float
    {
        $candidates = [];

        $patterns = [
            ['regex' => '/"amount"\s*:\s*(\d+(?:\.\d{1,2})?|\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/iu', 'weight' => 120, 'source' => 'json_amount'],
            ['regex' => '/R\$\s*(\d{1,3}(?:\.\d{3})*(?:,\d{2})|\d+(?:,\d{2}))/iu', 'weight' => 100, 'source' => 'currency'],
            ['regex' => '/(?:valor|total|quantia|pago|enviado|recebido)\s*[:\s]*R?\$?\s*(\d{1,3}(?:\.\d{3})*(?:,\d{2})|\d+(?:,\d{2}))/iu', 'weight' => 90, 'source' => 'label'],
            ['regex' => '/(\d{1,3}(?:\.\d{3})+,\d{2})/u', 'weight' => 70, 'source' => 'br_format'],
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern['regex'], $text, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $parsed = $this->parse($match[0]);
                if ($parsed === null || $parsed <= 0) {
                    continue;
                }

                $contextStart = max(0, $match[1] - 40);
                $context = mb_substr($text, $contextStart, 80);

                $score = $pattern['weight'];
                if (preg_match('/pix|transfer|pagamento|comprovante/iu', $context)) {
                    $score += 25;
                }
                if (preg_match('/taxa|tarifa|iof|juros/iu', $context)) {
                    $score -= 40;
                }

                $score += min(30, (int) log10(max($parsed, 1)) * 10);

                if ($filenameHint !== null && abs($parsed - $filenameHint) < 0.01) {
                    $score += 200;
                }

                $candidates[] = [
                    'amount' => $parsed,
                    'score' => $score,
                    'source' => $pattern['source'],
                    'raw' => $match[0],
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0]['amount'];
    }
}
