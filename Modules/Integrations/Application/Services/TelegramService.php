<?php

namespace Modules\Integrations\Application\Services;

use App\Core\Support\NotificationDestinationNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Integrations\Infrastructure\Models\WebhookLog;

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

        try {
            $response = Http::external()->timeout(15)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram send exception', [
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Falha ao conectar na API do Telegram. Verifique internet/DNS/firewall do servidor e tente novamente.'];
        }

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

        if (str_starts_with((string) $chatId, 'phone:+')) {
            $resolved = $this->discoverChatIdByPhone($token, (string) $chatId);
            if (! $resolved) {
                return [
                    'ok' => false,
                    'error' => $this->unresolvedDestinationMessage((string) $chatId),
                ];
            }

            $retry = $this->send($resolved, $message, $token);
            if ($retry['ok']) {
                return ['ok' => true, 'chat_id' => $resolved];
            }

            return $retry;
        }

        $result = $this->send($chatId, $message, $token);

        if ($result['ok']) {
            return $result;
        }

        if ($this->shouldResolveDestination((string) $chatId)) {
            $resolved = str_starts_with((string) $chatId, 'phone:+')
                ? $this->discoverChatIdByPhone($token, (string) $chatId)
                : $this->discoverChatId($token, (string) $chatId);

            if ($resolved) {
                $retry = $this->send($resolved, $message, $token);
                if ($retry['ok']) {
                    return ['ok' => true, 'chat_id' => $resolved];
                }

                return $retry;
            }

            return [
                'ok' => false,
                'error' => $this->unresolvedDestinationMessage((string) $chatId),
            ];
        }

        return $result;
    }

    public function discoverChatIdByPhone(?string $botToken, string $destination): ?string
    {
        if (! $botToken) {
            return null;
        }

        $digits = NotificationDestinationNormalizer::telegramPhoneDigits($destination);
        if (! $digits) {
            return null;
        }

        return $this->discoverChatIdFromWebhookLogs(phoneDigits: $digits);
    }

    /**
     * Resolve @usuario para chat_id numérico via getChat, webhooks recebidos ou getUpdates.
     */
    public function discoverChatId(?string $botToken, string $destination): ?string
    {
        if (! $botToken) {
            return null;
        }

        $chatRef = str_starts_with($destination, '@') ? $destination : '@'.$destination;

        try {
            $chat = Http::external()->timeout(10)
                ->get("https://api.telegram.org/bot{$botToken}/getChat", ['chat_id' => $chatRef]);

            if ($chat->successful()) {
                $id = $chat->json('result.id');

                return $id !== null ? (string) $id : null;
            }
        } catch (\Throwable) {
            // segue para getUpdates
        }

        $username = Str::lower(ltrim($chatRef, '@'));
        $webhookChatId = $this->discoverChatIdFromWebhookLogs(username: $username);
        if ($webhookChatId) {
            return $webhookChatId;
        }

        try {
            $updates = Http::external()->timeout(10)->get("https://api.telegram.org/bot{$botToken}/getUpdates");
        } catch (\Throwable) {
            return null;
        }

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

    protected function shouldResolveDestination(string $chatId): bool
    {
        return str_starts_with($chatId, '@') || str_starts_with($chatId, 'phone:+');
    }

    protected function unresolvedDestinationMessage(string $chatId): string
    {
        if (str_starts_with($chatId, 'phone:+')) {
            return 'Não achamos seu chat pelo telefone. Abra o bot no Telegram, envie /start e compartilhe seu contato com o bot. '
                .'Se persistir, use o código numérico que o @userinfobot mostra (ex.: 123456789) no campo destino.';
        }

        return 'Não achamos seu chat. Abra o bot no Telegram, envie /start e clique em Testar de novo. '
            .'Se persistir, use o código numérico que o @userinfobot mostra (ex.: 123456789) no campo destino.';
    }

    protected function discoverChatIdFromWebhookLogs(?string $username = null, ?string $phoneDigits = null): ?string
    {
        if (! $username && ! $phoneDigits) {
            return null;
        }

        try {
            $logs = WebhookLog::query()
                ->where('provider', 'telegram')
                ->latest()
                ->limit(200)
                ->get(['payload']);
        } catch (\Throwable) {
            return null;
        }

        foreach ($logs as $log) {
            $message = data_get($log->payload, 'message')
                ?? data_get($log->payload, 'edited_message');
            if (! is_array($message)) {
                continue;
            }

            $chat = $message['chat'] ?? [];
            if (($chat['type'] ?? '') !== 'private') {
                continue;
            }

            $from = $message['from'] ?? [];
            $fromUser = isset($from['username']) ? Str::lower($from['username']) : null;
            $chatUser = isset($chat['username']) ? Str::lower($chat['username']) : null;
            $contactPhone = isset($message['contact']['phone_number'])
                ? NotificationDestinationNormalizer::telegramPhoneDigits((string) $message['contact']['phone_number'])
                : null;

            $matchesUsername = $username && ($fromUser === $username || $chatUser === $username);
            $matchesPhone = $phoneDigits && $contactPhone === $phoneDigits;
            if (! $matchesUsername && ! $matchesPhone) {
                continue;
            }

            $id = $chat['id'] ?? $from['id'] ?? null;
            if ($id !== null) {
                return (string) $id;
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, path?: string, mime?: string, error?: string}
     */
    public function downloadFile(string $fileId, ?string $botToken = null): array
    {
        $token = $botToken ?? config('financial.integrations.telegram.bot_token');
        if (! $token) {
            return ['ok' => false, 'error' => 'Token do bot não configurado.'];
        }

        try {
            $meta = $this->telegramGet($token, 'getFile', ['file_id' => $fileId], timeout: 15);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => $this->downloadConnectionError($e)];
        }

        if ($meta->failed()) {
            return ['ok' => false, 'error' => 'Arquivo não encontrado no Telegram.'];
        }

        $filePath = $meta->json('result.file_path');
        if (! is_string($filePath) || $filePath === '') {
            return ['ok' => false, 'error' => 'Caminho do arquivo inválido.'];
        }

        $url = "https://api.telegram.org/file/bot{$token}/{$filePath}";

        try {
            $binary = $this->telegramGet($url, null, [], timeout: 60, isAbsoluteUrl: true);
        } catch (ConnectionException $e) {
            Log::warning('Telegram file download failed', [
                'file_id' => $fileId,
                'file_path' => $filePath,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $this->downloadConnectionError($e)];
        }

        if ($binary->failed()) {
            return ['ok' => false, 'error' => 'Falha ao baixar arquivo do Telegram.'];
        }

        $body = $binary->body();
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION) ?: '');
        if ($ext === '') {
            $ext = $this->guessExtensionFromContent($body);
        }
        $tmp = sys_get_temp_dir().'/tg_'.uniqid().'.'.$ext;
        file_put_contents($tmp, $body);

        $mime = match (strtolower($ext)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'xml' => 'application/xml',
            default => 'image/jpeg',
        };

        return ['ok' => true, 'path' => $tmp, 'mime' => $mime];
    }

    protected function guessExtensionFromContent(string $body): string
    {
        $prefix = ltrim(substr($body, 0, 120));

        if (str_starts_with($prefix, '%PDF')) {
            return 'pdf';
        }

        if (str_starts_with($prefix, '<?xml') || str_contains($prefix, '<nfeProc')) {
            return 'xml';
        }

        return 'jpg';
    }

    /**
     * GET com retentativas para falhas transitórias (reset de conexão, timeout).
     *
     * @param  array<string, mixed>  $query
     */
    protected function telegramGet(
        string $tokenOrUrl,
        ?string $method,
        array $query = [],
        int $timeout = 30,
        bool $isAbsoluteUrl = false,
    ): Response {
        $url = $isAbsoluteUrl
            ? $tokenOrUrl
            : "https://api.telegram.org/bot{$tokenOrUrl}/{$method}";

        $attempts = max(1, (int) config('financial.integrations.telegram.download_retries', 3));
        $delayMs = max(100, (int) config('financial.integrations.telegram.download_retry_delay_ms', 800));

        return Http::external()
            ->timeout($timeout)
            ->retry(
                $attempts,
                $delayMs,
                function (\Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && $exception->response !== null
                        && $exception->response->serverError();
                },
                throw: true,
            )
            ->get($url, $query);
    }

    protected function downloadConnectionError(ConnectionException $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Connection reset') || str_contains($msg, 'cURL error 35')) {
            return 'Conexão com o Telegram caiu ao baixar a imagem. Tente reenviar a foto em alguns segundos.';
        }

        if (str_contains($msg, 'timed out') || str_contains($msg, 'cURL error 28')) {
            return 'Download do Telegram demorou demais. Reenvie a foto ou use uma imagem menor.';
        }

        return 'Não foi possível baixar o arquivo do Telegram agora. Reenvie a foto em instantes.';
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
