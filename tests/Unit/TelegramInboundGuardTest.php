<?php

namespace Tests\Unit;

use App\Core\Support\TelegramInboundGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramInboundGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://financialiq.example.com',
            'financial.integrations.telegram.bot_token' => 'test-token',
            'financial.integrations.telegram.inbound_enabled' => true,
            'financial.integrations.telegram.inbound_sync' => true,
            'financial.integrations.telegram.webhook_secret' => null,
        ]);
    }

    public function test_diagnose_flags_missing_webhook(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-token/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => ['url' => '', 'last_error_message' => ''],
            ]),
            'https://financialiq.example.com/api/v1/webhooks/telegram' => Http::response(['ok' => true]),
        ]);

        $guard = new TelegramInboundGuard;
        $codes = array_column($guard->diagnose(), 'code');

        $this->assertContains('webhook_missing', $codes);
        $this->assertTrue($guard->needsFix());
    }

    public function test_diagnose_flags_webhook_url_mismatch(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-token/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://old.nip.io/api/v1/webhooks/telegram',
                    'last_error_message' => '',
                ],
            ]),
            'https://financialiq.example.com/api/v1/webhooks/telegram' => Http::response(['ok' => true]),
        ]);

        $guard = new TelegramInboundGuard;
        $codes = array_column($guard->diagnose(), 'code');

        $this->assertContains('webhook_url_mismatch', $codes);
    }

    public function test_apply_fix_registers_expected_webhook_without_dropping_pending(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-token/getWebhookInfo' => Http::sequence()
                ->push(['ok' => true, 'result' => ['url' => '', 'last_error_message' => '']])
                ->push(['ok' => true, 'result' => [
                    'url' => 'https://financialiq.example.com/api/v1/webhooks/telegram',
                    'last_error_message' => '',
                ]]),
            'https://api.telegram.org/bottest-token/setWebhook' => function ($request) {
                $this->assertFalse($request['drop_pending_updates']);

                return Http::response(['ok' => true, 'result' => true]);
            },
            'https://financialiq.example.com/api/v1/webhooks/telegram' => Http::response(['ok' => true]),
        ]);

        $guard = new TelegramInboundGuard;
        $result = $guard->applyFix();

        $this->assertContains('webhook_registered', $result['fixed']);
        $this->assertFalse($guard->needsFix());
    }

    public function test_expected_webhook_url_requires_https_app_url(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $guard = new TelegramInboundGuard;

        $this->assertNull($guard->expectedWebhookUrl());
    }
}
