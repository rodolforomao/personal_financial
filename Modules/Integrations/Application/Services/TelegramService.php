<?php

namespace Modules\Integrations\Application\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramService
{
    /**
     * @return array{ok: bool, error?: string}
     */
    public function send(string $chatId, string $message, ?string $botToken = null): array
    {
        $token = $botToken ?? config('financial.integrations.telegram.bot_token');
        if (! $token) {
            return ['ok' => false, 'error' => 'Token do bot não configurado no servidor (.env TELEGRAM_BOT_TOKEN).'];
        }

        $response = Http::timeout(15)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

        if ($response->successful()) {
            return ['ok' => true];
        }

        $description = $response->json('description') ?? $response->body();
        Log::warning('Telegram send failed', [
            'chat_id' => $chatId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['ok' => false, 'error' => $this->friendlyError($description, $chatId)];
    }

    /**
     * @return array{ok: bool, error?: string, chat_id?: string}
     */
    public function sendWithConfig(array $config, string $message): array
    {
        $token = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? '';

        $result = $this->send($chatId, $message, $token);

        if ($result['ok']) {
            return $result;
        }

        if (str_starts_with((string) $chatId, '@')) {
            $resolved = $this->discoverChatId($token, $chatId);
            if ($resolved) {
                $retry = $this->send($resolved, $message, $token);
                if ($retry['ok']) {
                    return ['ok' => true, 'chat_id' => $resolved];
                }

                return $retry;
            }

            return [
                'ok' => false,
                'error' => 'Não achamos seu chat. Abra o bot no Telegram, envie /start e clique em Testar de novo. '
                    .'Se persistir, use o número que o @userinfobot mostra (ex.: 123456789) no campo destino.',
            ];
        }

        return $result;
    }

    /**
     * Resolve @usuario para chat_id numérico via getChat ou getUpdates (após /start).
     */
    public function discoverChatId(?string $botToken, string $destination): ?string
    {
        if (! $botToken) {
            return null;
        }

        $chatRef = str_starts_with($destination, '@') ? $destination : '@'.$destination;

        try {
            $chat = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$botToken}/getChat", ['chat_id' => $chatRef]);

            if ($chat->successful()) {
                $id = $chat->json('result.id');

                return $id !== null ? (string) $id : null;
            }
        } catch (\Throwable) {
            // segue para getUpdates
        }

        $username = Str::lower(ltrim($chatRef, '@'));
        $updates = Http::timeout(10)->get("https://api.telegram.org/bot{$botToken}/getUpdates");

        if (! $updates->successful()) {
            return null;
        }

        $latestMatch = null;
        foreach (array_reverse($updates->json('result', [])) as $update) {
            $message = $update['message'] ?? $update['edited_message'] ?? null;
            if (! $message) {
                continue;
            }

            $chat = $message['chat'] ?? [];
            $from = $message['from'] ?? [];
            $fromUser = isset($from['username']) ? Str::lower($from['username']) : null;
            $chatUser = isset($chat['username']) ? Str::lower($chat['username']) : null;

            if ($fromUser === $username || $chatUser === $username) {
                return (string) ($chat['id'] ?? $from['id'] ?? '');
            }

            if ($chat['type'] === 'private' && $fromUser === $username) {
                $latestMatch = (string) ($chat['id'] ?? '');
            }
        }

        if ($latestMatch) {
            return $latestMatch;
        }

        // último chat privado que falou com o bot (fallback após /start)
        foreach (array_reverse($updates->json('result', [])) as $update) {
            $message = $update['message'] ?? null;
            if (! $message) {
                continue;
            }
            $chat = $message['chat'] ?? [];
            if (($chat['type'] ?? '') === 'private') {
                return (string) $chat['id'];
            }
        }

        return null;
    }

    protected function friendlyError(string $description, string $chatId): string
    {
        $lower = Str::lower($description);

        if (str_contains($lower, 'chat not found')) {
            return str_starts_with($chatId, '@')
                ? 'Chat não encontrado para '.$chatId.'. Envie /start ao bot e use o ID numérico do @userinfobot se precisar.'
                : 'Chat não encontrado ('.$chatId.'). Envie /start ao bot correto e confira o ID.';
        }

        if (str_contains($lower, 'bot was blocked')) {
            return 'Você bloqueou o bot no Telegram. Desbloqueie e envie /start novamente.';
        }

        if (str_contains($lower, 'unauthorized') || str_contains($lower, 'invalid token')) {
            return 'Token do bot inválido. Confira TELEGRAM_BOT_TOKEN no .env ou o token do seu bot.';
        }

        return 'Telegram: '.$description;
    }
}
