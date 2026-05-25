<?php

namespace Modules\Integrations\Application\Jobs;

use App\Application\Services\PlatformOperationsGuide;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Integrations\Application\Services\TelegramInboundService;
use Modules\Integrations\Application\Services\TelegramService;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public array $update,
    ) {
        $this->onQueue(config('financial.queues.notifications', 'notifications'));
    }

    public function handle(TelegramInboundService $inbound, TelegramService $telegram): void
    {
        try {
            $inbound->handleUpdate($this->update);
        } catch (\Throwable $e) {
            Log::error('Telegram inbound failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $chatId = $this->extractChatId();
            $token = config('financial.integrations.telegram.bot_token');
            if ($chatId && $token) {
                $telegram->send(
                    $chatId,
                    "⚠️ Erro ao processar sua mensagem.\n".
                    "Tente enviar a foto do comprovante de novo ou use: Receita de 356 descrição\n\n".
                    'Detalhe: '.mb_substr($e->getMessage(), 0, 120),
                    $token
                );
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Telegram inbound job failed', ['message' => $exception->getMessage()]);

        $chatId = $this->extractChatId();
        $token = config('financial.integrations.telegram.bot_token');
        if (! $chatId || ! $token) {
            return;
        }

        $hint = mb_substr(app(PlatformOperationsGuide::class)->recommendedProcessesPlain(), 0, 500);
        app(TelegramService::class)->send(
            $chatId,
            "⚠️ Não consegui processar (timeout ou fila parada).\n\n{$hint}\n\nDetalhe: /ops no bot.",
            $token
        );
    }

    protected function extractChatId(): ?string
    {
        $message = $this->update['message'] ?? $this->update['edited_message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $id = $message['chat']['id'] ?? null;

        return $id !== null ? (string) $id : null;
    }
}
