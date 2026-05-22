<?php

namespace Tests\Unit;

use App\Core\Support\NotificationDestinationNormalizer;
use PHPUnit\Framework\TestCase;

class NotificationDestinationNormalizerTest extends TestCase
{
    public function test_telegram_accepts_at_username(): void
    {
        $this->assertSame('@rodolforomaobr', NotificationDestinationNormalizer::telegram('@rodolforomaobr'));
        $this->assertSame('@rodolforomaobr', NotificationDestinationNormalizer::telegram('rodolforomaobr'));
    }

    public function test_telegram_accepts_t_me_link(): void
    {
        $this->assertSame('@rodolforomaobr', NotificationDestinationNormalizer::telegram('https://t.me/rodolforomaobr'));
    }

    public function test_telegram_accepts_numeric_chat_id(): void
    {
        $this->assertSame('123456789', NotificationDestinationNormalizer::telegram('123456789'));
        $this->assertSame('-100123456789', NotificationDestinationNormalizer::telegram('-100123456789'));
    }

    public function test_whatsapp_normalizes_brazilian_number(): void
    {
        $this->assertSame('5511999999999', NotificationDestinationNormalizer::whatsapp('+55 (11) 99999-9999'));
        $this->assertSame('5511999999999', NotificationDestinationNormalizer::whatsapp('5511999999999'));
    }
}
