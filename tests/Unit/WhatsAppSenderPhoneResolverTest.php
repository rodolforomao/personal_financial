<?php

namespace Tests\Unit;

use Modules\Integrations\Application\Services\WhatsAppSenderPhoneResolver;
use PHPUnit\Framework\TestCase;

class WhatsAppSenderPhoneResolverTest extends TestCase
{
    public function test_resolves_standard_whatsapp_jid(): void
    {
        $resolver = new WhatsAppSenderPhoneResolver;

        $phone = $resolver->resolve(
            ['remoteJid' => '5561999013675@s.whatsapp.net'],
            [],
            [],
        );

        $this->assertSame('5561999013675', $phone);
    }

    public function test_prefers_remote_jid_alt_over_lid(): void
    {
        $resolver = new WhatsAppSenderPhoneResolver;

        $phone = $resolver->resolve(
            [
                'remoteJid' => '84122101903380@lid',
                'remoteJidAlt' => '5561999013675@s.whatsapp.net',
            ],
            [],
            [],
        );

        $this->assertSame('5561999013675', $phone);
    }
}
