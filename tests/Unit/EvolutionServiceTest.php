<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Integrations\Application\Services\EvolutionService;
use Modules\Integrations\Application\Services\WhatsAppService;
use Tests\TestCase;

class EvolutionServiceTest extends TestCase
{
    protected function evolutionConfig(): void
    {
        config([
            'financial.integrations.evolution.api_url' => 'http://evolution.test',
            'financial.integrations.evolution.api_key' => 'test-key',
            'financial.integrations.evolution.instance_name' => 'financial-system',
        ]);
    }

    public function test_send_text_posts_to_evolution_endpoint(): void
    {
        $this->evolutionConfig();

        Http::fake([
            'evolution.test/message/sendText/financial-system' => Http::response(['key' => ['id' => 'msg-1']], 201),
        ]);

        $this->assertTrue(app(EvolutionService::class)->sendText('5511999999999', 'Alerta teste'));
    }

    public function test_whatsapp_service_uses_evolution_for_system_config(): void
    {
        $this->evolutionConfig();
        config(['financial.integrations.whatsapp.provider' => 'evolution']);

        Http::fake([
            'evolution.test/message/sendText/financial-system' => Http::response([], 200),
        ]);

        $ok = app(WhatsAppService::class)->sendWithConfig([
            'phone' => '5511888777666',
            'provider' => 'evolution',
            'source' => 'system',
        ], 'Olá');

        $this->assertTrue($ok);
    }

    public function test_send_returns_false_when_not_configured(): void
    {
        config([
            'financial.integrations.evolution.api_url' => '',
            'financial.integrations.evolution.api_key' => '',
            'financial.integrations.evolution.instance_name' => '',
        ]);

        $this->assertFalse(app(EvolutionService::class)->sendText('5511999999999', 'x'));
    }
}
