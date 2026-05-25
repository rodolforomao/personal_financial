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
     *     type: ?string
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
        ];

        if ($text === '') {
            return $result;
        }

        if (preg_match_all(
            '/\b(contraparte|categoria|empresa|opera[çc][aã]o|apartamento|apto|unidade|descri[çc][aã]o|tipo|receita|gasto|despesa)\s*:\s*([^.;]+)/iu',
            $text,
            $matches,
            PREG_SET_ORDER,
        )) {
            foreach ($matches as $match) {
                $label = mb_strtolower(trim($match[1]));
                $value = trim($match[2], " \t\n\r\0\x0B,.");
                if ($value === '') {
                    continue;
                }

                if ($label === 'contraparte') {
                    $result['counterparty'] = $value;
                } elseif ($label === 'categoria') {
                    $result['category'] = $value;
                } elseif ($label === 'empresa') {
                    $result['company'] = $value;
                } elseif (str_starts_with($label, 'opera')) {
                    $result['operation'] = $value;
                } elseif (in_array($label, ['apartamento', 'apto', 'unidade'], true)) {
                    $result['unit'] = $value;
                } elseif (str_starts_with($label, 'descri')) {
                    $result['description'] = $value;
                } elseif ($label === 'tipo') {
                    $result['type'] = $value;
                } elseif (in_array($label, ['receita', 'gasto', 'despesa'], true)) {
                    $result['type'] = $label;
                    $result['description'] = $value;
                }
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
