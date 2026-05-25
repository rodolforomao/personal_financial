<?php

namespace Modules\Integrations\Application\Services;

/**
 * Interpreta texto do usuário enquanto aguarda confirmação do comprovante.
 * Ex.: "Contraparte: presente da shilene. Categoria: shilene. Empresa pessoal, operação geral."
 */
class ReceiptDraftSupplementParser
{
    /**
     * @return array{
     *     counterparty: ?string,
     *     category: ?string,
     *     company: ?string,
     *     operation: ?string,
     *     unit: ?string,
     *     description: ?string,
     *     type: ?string,
     *     bank: ?string,
     *     funding_source: ?string,
     *     payment_method: ?string
     * }
     */
    public function parse(string $text): array
    {
        $text = trim($text);
        $result = [
            'counterparty' => null,
            'category' => null,
            'company' => null,
            'operation' => null,
            'unit' => null,
            'description' => null,
            'type' => null,
            'bank' => null,
            'funding_source' => null,
            'payment_method' => null,
        ];

        if ($text === '') {
            return $result;
        }

        $labelPattern = $this->labelPattern();

        if (preg_match_all(
            '/\b('.$labelPattern.')\s*:\s*([^.;]+)/iu',
            $text,
            $matches,
            PREG_SET_ORDER,
        )) {
            foreach ($matches as $match) {
                $this->applyField($result, $match[1], $match[2]);
            }
        }

        foreach ($this->clauses($text) as $clause) {
            if (! preg_match('/^((?:'.$labelPattern.')(?:\s*(?:\be\b|\/|\+|,)\s*(?:'.$labelPattern.'))*)\s+(.+)$/iu', $clause, $match)) {
                continue;
            }

            $value = $this->cleanValue($match[2]);
            if ($value === '') {
                continue;
            }

            foreach ($this->labelsFromGroup($match[1]) as $label) {
                $this->applyField($result, $label, $value);
            }
        }

        if ($result['company'] === null || $result['operation'] === null) {
            if (preg_match('/\bempresa\s+([^,]+?)\s*,\s*opera[çc][aã]o\s+(.+)$/iu', $text, $shorthand)) {
                $result['company'] ??= trim($shorthand[1]);
                $result['operation'] ??= trim($shorthand[2], " \t\n\r\0\x0B,.");
            }
        }

        return $result;
    }

    protected function labelPattern(): string
    {
        return 'contraparte|categoria|empresa|opera[çc][aã]o|apartamento|apto|unidade|descri[çc][aã]o|tipo|receita|gasto|despesa|despeza|fonte|banco|meio(?:\s+de\s+pagamento)?|forma(?:\s+de\s+pagamento)?|pagamento';
    }

    /**
     * @return list<string>
     */
    protected function clauses(string $text): array
    {
        $parts = preg_split('/[.;\n]+/u', $text) ?: [];
        $clauses = [];

        foreach ($parts as $part) {
            $subparts = preg_split('/,\s*(?=(?:'.$this->labelPattern().')\b)/iu', $part) ?: [];
            foreach ($subparts as $subpart) {
                $clause = trim($subpart);
                if ($clause !== '') {
                    $clauses[] = $clause;
                }
            }
        }

        return $clauses;
    }

    /**
     * @return list<string>
     */
    protected function labelsFromGroup(string $group): array
    {
        $parts = preg_split('/\s*(?:\be\b|\/|\+|,)\s*/iu', $group) ?: [];

        return array_values(array_filter(array_map(
            fn (string $label): string => $this->normalizeLabel($label),
            $parts,
        )));
    }

    /**
     * @param  array<string, ?string>  $result
     */
    protected function applyField(array &$result, string $rawLabel, string $rawValue): void
    {
        $label = $this->normalizeLabel($rawLabel);
        $value = $this->cleanValue($rawValue);
        if ($value === '') {
            return;
        }

        if ($label === 'contraparte') {
            $result['counterparty'] = $value;
        } elseif ($label === 'categoria') {
            $result['category'] = $value;
        } elseif ($label === 'empresa') {
            $result['company'] = $value;
        } elseif ($label === 'operacao') {
            $result['operation'] = $value;
        } elseif (in_array($label, ['apartamento', 'apto', 'unidade'], true)) {
            $result['unit'] = $value;
        } elseif ($label === 'descricao') {
            $result['description'] = $value;
        } elseif ($label === 'tipo') {
            $result['type'] = $value;
        } elseif (in_array($label, ['receita', 'gasto', 'despesa', 'despeza'], true)) {
            $result['type'] = $label;
            $result['description'] = $value;
        } elseif ($label === 'fonte') {
            $result['funding_source'] = $value;
        } elseif ($label === 'banco') {
            $result['bank'] = $value;
            $result['funding_source'] ??= $value;
        } elseif (in_array($label, ['meio de pagamento', 'forma de pagamento', 'pagamento'], true)) {
            $result['payment_method'] = $value;
        }
    }

    protected function normalizeLabel(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = str_replace(['ç', 'ã'], ['c', 'a'], $label);
        $label = preg_replace('/\s+/u', ' ', $label) ?: $label;

        return match (true) {
            str_starts_with($label, 'opera') => 'operacao',
            str_starts_with($label, 'descri') => 'descricao',
            str_starts_with($label, 'meio') => 'meio de pagamento',
            str_starts_with($label, 'forma') => 'forma de pagamento',
            default => $label,
        };
    }

    protected function cleanValue(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B,.:;-");
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function hasAnyField(array $fields): bool
    {
        foreach ($fields as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
