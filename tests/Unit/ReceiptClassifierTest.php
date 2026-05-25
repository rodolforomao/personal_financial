<?php

namespace Tests\Unit;

use Modules\Integrations\Application\Services\ReceiptClassifier;
use PHPUnit\Framework\TestCase;

class ReceiptClassifierTest extends TestCase
{
    public function test_detects_banco_inter_pix_sent(): void
    {
        $text = 'Pix enviado R$ 5.000,00 BANCO INTER Quinta-feira 21/05/2026';
        $result = (new ReceiptClassifier)->classify($text);

        $this->assertSame('banco_inter', $result['bank_slug']);
        $this->assertSame('Banco Inter', $result['bank']);
        $this->assertSame('pix_sent', $result['receipt_type']);
    }
}
