<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Integrations\Application\Services\TelegramInboundService;
use Tests\TestCase;

class TelegramInboundServiceTest extends TestCase
{
    public function test_start_links_saved_username_to_numeric_chat_id(): void
    {
        config([
            'financial.integrations.telegram.bot_token' => 'token',
            'financial.integrations.telegram.bot_username' => 'financial_bot',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $user = User::factory()->create([
            'preferences' => [
                'notifications' => [
                    'telegram_destination_display' => '@rodolforomaobr',
                    'telegram_chat_id' => '@rodolforomaobr',
                ],
            ],
        ]);

        $result = app(TelegramInboundService::class)->handleUpdate([
            'message' => [
                'message_id' => 10,
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
        ]);

        $this->assertTrue($result['handled']);
        $this->assertSame('help', $result['reply']);
        $this->assertSame(
            '987654321',
            $user->fresh()->preferences['notifications']['telegram_chat_id']
        );
    }

    public function test_shared_contact_links_saved_phone_to_numeric_chat_id(): void
    {
        config([
            'financial.integrations.telegram.bot_token' => 'token',
            'financial.integrations.telegram.bot_username' => 'financial_bot',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $user = User::factory()->create([
            'preferences' => [
                'notifications' => [
                    'telegram_destination_display' => '+5511999999999',
                    'telegram_chat_id' => 'phone:+5511999999999',
                ],
            ],
        ]);

        $result = app(TelegramInboundService::class)->handleUpdate([
            'message' => [
                'message_id' => 11,
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
        ]);

        $this->assertTrue($result['handled']);
        $this->assertSame('contact_linked', $result['reply']);
        $this->assertSame(
            '987654321',
            $user->fresh()->preferences['notifications']['telegram_chat_id']
        );
    }
}
