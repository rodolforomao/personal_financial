<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Integrations\Application\Services\TelegramInboundService;
use Modules\Integrations\Application\Services\TelegramService;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class TelegramStatementImportTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_telegram_ofx_document_uses_statement_import_workflow(): void
    {
        config(['financial.integrations.telegram.bot_token' => 'token']);

        $this->user->forceFill([
            'preferences' => [
                'notifications' => [
                    'telegram_chat_id' => '987654321',
                ],
            ],
        ])->save();

        $tmp = sys_get_temp_dir().'/telegram_statement_'.uniqid().'.ofx';
        copy(base_path('tests/fixtures/sample.ofx'), $tmp);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('downloadFile')
            ->once()
            ->with('file-ofx', 'token')
            ->andReturn([
                'ok' => true,
                'path' => $tmp,
                'mime' => 'application/x-ofx',
            ]);
        $telegram->shouldReceive('send')
            ->once()
            ->withArgs(fn ($chatId, $message) => $chatId === '987654321'
                && str_contains($message, 'Extrato importado pelo Telegram')
                && str_contains($message, 'Editar importadas em massa'))
            ->andReturn(['ok' => true]);
        $this->app->instance(TelegramService::class, $telegram);

        $result = app(TelegramInboundService::class)->handleUpdate([
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 987654321, 'type' => 'private'],
                'from' => ['id' => 987654321],
                'caption' => 'extrato do cartão de credito do nubank',
                'document' => [
                    'file_id' => 'file-ofx',
                    'file_name' => 'Nubank_2026-05-26.ofx',
                    'mime_type' => 'application/octet-stream',
                ],
            ],
        ]);

        $this->assertTrue($result['handled']);
        $this->assertSame('statement_import', $result['reply']);
        $this->assertSame(
            2,
            Transaction::query()
                ->where('workspace_id', $this->workspace->id)
                ->where('source', 'ofx')
                ->count(),
        );
    }
}
