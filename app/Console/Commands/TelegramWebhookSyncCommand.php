<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramWebhookSyncCommand extends Command
{
    protected $signature = 'telegram:webhook-sync
                            {--url= : URL pública do webhook (padrão: APP_URL/api/v1/webhooks/telegram)}
                            {--drop : Remove o webhook do Telegram}';

    protected $description = 'Registra (ou remove) o webhook do bot Telegram para receber mensagens e criar lançamentos';

    public function handle(): int
    {
        $token = config('financial.integrations.telegram.bot_token');
        if (! $token) {
            $this->error('Defina TELEGRAM_BOT_TOKEN no .env');

            return self::FAILURE;
        }

        if ($this->option('drop')) {
            $response = Http::post("https://api.telegram.org/bot{$token}/deleteWebhook");

            return $this->report($response->json(), 'Webhook removido.');
        }

        $url = $this->option('url')
            ?? rtrim((string) config('app.url'), '/').'/api/v1/webhooks/telegram';

        $payload = [
            'url' => $url,
            'allowed_updates' => ['message', 'edited_message'],
            'drop_pending_updates' => true,
        ];

        $secret = config('financial.integrations.telegram.webhook_secret');
        if ($secret) {
            $payload['secret_token'] = $secret;
        }

        $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

        return $this->report($response->json(), "Webhook registrado: {$url}");
    }

    protected function report(?array $body, string $successMessage): int
    {
        if (! ($body['ok'] ?? false)) {
            $this->error($body['description'] ?? 'Falha na API do Telegram');

            return self::FAILURE;
        }

        $this->info($successMessage);

        return self::SUCCESS;
    }
}
