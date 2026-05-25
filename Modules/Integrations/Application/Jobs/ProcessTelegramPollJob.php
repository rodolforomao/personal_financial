<?php

namespace Modules\Integrations\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Integrations\Application\Services\TelegramPollService;
use Modules\Integrations\Application\Services\TelegramService;

class ProcessTelegramPollJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public ?string $notifyChatId = null,
    ) {
        $this->onQueue(config('financial.queues.notifications', 'notifications'));
    }

    public function handle(TelegramPollService $poll, TelegramService $telegram): void
    {
        $result = $poll->pollOnce();

        if ($this->notifyChatId === null) {
            return;
        }

        $token = config('financial.integrations.telegram.bot_token');
        if (! $token) {
            return;
        }

        if (! ($result['ok'] ?? false)) {
            $telegram->send(
                $this->notifyChatId,
                '⚠️ Poll falhou: '.($result['error'] ?? 'erro desconhecido'),
                $token,
            );

            return;
        }

        $processed = (int) ($result['processed'] ?? 0);
        $handled = (int) ($result['handled'] ?? 0);
        $lines = $result['lines'] ?? [];

        $message = "📥 Poll concluído\n".
            "Updates: {$processed} | Processados: {$handled}";

        if ($lines !== []) {
            $message .= "\n\n".implode("\n", array_slice($lines, 0, 8));
            if (count($lines) > 8) {
                $message .= "\n… (+".(count($lines) - 8).' linhas)';
            }
        } elseif ($processed === 0) {
            $message .= "\n\nNenhuma mensagem nova no Telegram.";
        }

        $telegram->send($this->notifyChatId, $message, $token);

        Log::info('Telegram poll job finished', [
            'processed' => $processed,
            'handled' => $handled,
            'chat_id' => $this->notifyChatId,
        ]);
    }
}
