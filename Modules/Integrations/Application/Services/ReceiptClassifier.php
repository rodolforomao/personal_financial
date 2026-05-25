<?php

namespace Modules\Integrations\Application\Services;

class ReceiptClassifier
{
    /**
     * @return array{receipt_type: string, receipt_type_label: string, bank: ?string, bank_slug: ?string}
     */
    public function classify(string $text, ?string $bankFromOcr = null): array
    {
        $lower = mb_strtolower($text);
        $bank = $this->detectBank($lower, $bankFromOcr);
        $type = $this->detectReceiptType($lower);

        return [
            'receipt_type' => $type['slug'],
            'receipt_type_label' => $type['label'],
            'bank' => $bank['name'],
            'bank_slug' => $bank['slug'],
        ];
    }

    /**
     * @return array{name: ?string, slug: ?string}
     */
    protected function detectBank(string $lower, ?string $fromOcr): array
    {
        $banks = [
            'banco inter' => ['name' => 'Banco Inter', 'slug' => 'banco_inter'],
            'inter ' => ['name' => 'Banco Inter', 'slug' => 'banco_inter'],
            'nubank' => ['name' => 'Nubank', 'slug' => 'nubank'],
            'itau' => ['name' => 'Itaú', 'slug' => 'itau'],
            'bradesco' => ['name' => 'Bradesco', 'slug' => 'bradesco'],
            'santander' => ['name' => 'Santander', 'slug' => 'santander'],
            'caixa' => ['name' => 'Caixa', 'slug' => 'caixa'],
            'bb ' => ['name' => 'Banco do Brasil', 'slug' => 'bb'],
            'banco do brasil' => ['name' => 'Banco do Brasil', 'slug' => 'bb'],
            'stone' => ['name' => 'Stone', 'slug' => 'stone'],
            'c6 bank' => ['name' => 'C6 Bank', 'slug' => 'c6'],
            'mercado pago' => ['name' => 'Mercado Pago', 'slug' => 'mercado_pago'],
            'picpay' => ['name' => 'PicPay', 'slug' => 'picpay'],
            'sideswap' => ['name' => 'Sideswap', 'slug' => 'sideswap'],
            'wise' => ['name' => 'Wise', 'slug' => 'wise'],
        ];

        if ($fromOcr) {
            $ocrLower = mb_strtolower($fromOcr);
            foreach ($banks as $needle => $meta) {
                if (str_contains($ocrLower, trim($needle))) {
                    return $meta;
                }
            }
        }

        foreach ($banks as $needle => $meta) {
            if (str_contains($lower, $needle)) {
                return $meta;
            }
        }

        return ['name' => $fromOcr, 'slug' => null];
    }

    /**
     * @return array{slug: string, label: string}
     */
    protected function detectReceiptType(string $lower): array
    {
        if (preg_match('/pix\s+enviado|você\s+enviou|transferência\s+enviada/iu', $lower)) {
            return ['slug' => 'pix_sent', 'label' => 'PIX enviado'];
        }

        if (preg_match('/pix\s+recebido|você\s+recebeu|transferência\s+recebida|transfer_direction["\']?\s*:\s*["\']received/iu', $lower)) {
            return ['slug' => 'pix_received', 'label' => 'PIX recebido'];
        }

        if (preg_match('/pix\s+enviado|transferência\s+enviada|você\s+enviou/u', $lower)
            || preg_match('/\borigem\b/iu', $lower) && preg_match('/\bdestino\b/iu', $lower)
            && preg_match('/enviou|enviado|enviada/u', $lower)) {
            return ['slug' => 'pix_sent', 'label' => 'PIX enviado'];
        }

        if (preg_match('/pix\s+recebido|transferência\s+recebida|você\s+recebeu/u', $lower)
            || (preg_match('/\bdestino\b/iu', $lower) && preg_match('/\borigem\b/iu', $lower)
                && preg_match('/recebeu|recebido|recebida/u', $lower))) {
            return ['slug' => 'pix_received', 'label' => 'PIX recebido'];
        }

        if (preg_match('/\bboleto\b/iu', $lower)) {
            return ['slug' => 'boleto', 'label' => 'Boleto'];
        }

        if (preg_match('/\bted\b|\bdoc\b/iu', $lower)) {
            return ['slug' => 'transfer', 'label' => 'Transferência bancária'];
        }

        if (preg_match('/cartão|compra\s+no\s+débito|débito/iu', $lower)) {
            return ['slug' => 'card', 'label' => 'Cartão'];
        }

        if (preg_match('/\bpix\b/iu', $lower)) {
            return ['slug' => 'pix', 'label' => 'Comprovante PIX'];
        }

        return ['slug' => 'bank_receipt', 'label' => 'Comprovante bancário'];
    }
}
