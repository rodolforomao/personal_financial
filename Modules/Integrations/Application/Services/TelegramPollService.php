<?php

namespace Modules\Integrations\Application\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramPollService
{
    public function __construct(
        protected TelegramInboundService $inbound,
    ) {}

    /**
     * Um ciclo de long polling (equivalente a `telegram:poll --once`).
     *
     * @return array{ok: bool, processed: int, handled: int, error?: string, lines: list<string>}
     */
    public function pollOnce(?int $timeoutSeconds = null): array
    {
        $token = config('financial.integrations.telegram.bot_token');
        if (! $token) {
            return ['ok' => false, 'processed' => 0, 'handled' => 0, 'error' => 'TELEGRAM_BOT_TOKEN ausente', 'lines' => []];
        }

        if (! config('financial.integrations.telegram.inbound_enabled', true)) {
            return ['ok' => false, 'processed' => 0, 'handled' => 0, 'error' => 'TELEGRAM_INBOUND_ENABLED=false', 'lines' => []];
        }

        $offsetKey = 'telegram_poll_offset';
        $offset = Cache::get($offsetKey);
        $timeout = min(50, max(1, $timeoutSeconds ?? (int) config('financial.integrations.telegram.poll_timeout', 25)));

        $response = Http::external()->get("https://api.telegram.org/bot{$token}/getUpdates", array_filter([
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'edited_message']),
        ]));

        if (! $response->successful()) {
            $error = $response->json('description') ?? $response->body();

            return ['ok' => false, 'processed' => 0, 'handled' => 0, 'error' => (string) $error, 'lines' => []];
        }

        $updates = $response->json('result', []);
        $handled = 0;
        $lines = [];

        foreach ($updates as $update) {
            $updateId = (int) ($update['update_id'] ?? 0);
            if ($updateId > 0) {
                Cache::forever($offsetKey, $updateId + 1);
            }

            try {
                $result = $this->inbound->handleUpdate($update);
            } catch (\Throwable $e) {
                Log::error('Telegram poll update failed', [
                    'update_id' => $update['update_id'] ?? null,
                    'message' => $e->getMessage(),
                ]);
                $lines[] = '✗ erro: '.$e->getMessage();

                continue;
            }

            if ($result['handled'] ?? false) {
                $handled++;
                $msg = $update['message'] ?? $update['edited_message'] ?? [];
                $text = $msg['text'] ?? $msg['caption'] ?? '';
                $lines[] = '✓ '.($result['reply'] ?? 'ok').': '.mb_substr((string) $text, 0, 60);
            }
        }

        return [
            'ok' => true,
            'processed' => count($updates),
            'handled' => $handled,
            'lines' => $lines,
        ];
    }
}
