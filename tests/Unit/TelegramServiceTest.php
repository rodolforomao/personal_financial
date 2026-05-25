<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Integrations\Application\Services\TelegramService;
use Modules\Integrations\Infrastructure\Models\WebhookLog;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    public function test_discovers_chat_id_from_recent_webhook_logs_when_get_updates_is_unavailable(): void
    {
        WebhookLog::query()->create([
            'provider' => 'telegram',
            'event' => 'message',
            'payload' => [
                'message' => [
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                        'username' => 'RodolfoRomaoBr',
                    ],
                    'from' => [
                        'id' => 987654321,
                        'username' => 'RodolfoRomaoBr',
                    ],
                    'text' => '/start',
                ],
            ],
            'status' => 'received',
        ]);

        Http::fake([
            'https://api.telegram.org/bottoken/getChat*' => Http::response([
                'ok' => false,
                'description' => 'Bad Request: chat not found',
            ], 400),
            'https://api.telegram.org/bottoken/getUpdates*' => Http::response([
                'ok' => false,
                'description' => 'Conflict: webhook is active',
            ], 409),
        ]);

        $chatId = app(TelegramService::class)->discoverChatId('token', '@RodolfoRomaoBr');

        $this->assertSame('987654321', $chatId);
    }

    public function test_discovers_chat_id_from_shared_contact_phone(): void
    {
        WebhookLog::query()->create([
            'provider' => 'telegram',
            'event' => 'message',
            'payload' => [
                'message' => [
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => 987654321,
                    ],
                    'contact' => [
                        'phone_number' => '+55 (11) 99999-9999',
                    ],
                ],
            ],
            'status' => 'received',
        ]);

        $chatId = app(TelegramService::class)->discoverChatIdByPhone('token', 'phone:+5511999999999');

        $this->assertSame('987654321', $chatId);
    }
}
