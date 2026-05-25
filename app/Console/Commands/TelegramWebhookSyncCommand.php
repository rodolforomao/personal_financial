<?php

namespace App\Console\Commands;

use App\Application\Services\PlatformOperationsGuide;
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
            $response = Http::external()->post("https://api.telegram.org/bot{$token}/deleteWebhook");

            if (! ($response->json('ok') ?? false)) {
                $this->error($response->json('description') ?? 'Falha na API do Telegram');

                return self::FAILURE;
            }

            $this->info('Webhook removido.');
            $this->line('Dev (porta do serve, ex. 8001): php artisan schedule:work');
            $this->line('Ou envie /poll no bot. Guia completo: /ops no Telegram ou /integrations/notifications');

            return self::SUCCESS;
        }

        $url = $this->option('url')
            ?? rtrim((string) config('app.url'), '/').'/api/v1/webhooks/telegram';

        if (! str_starts_with($url, 'https://')) {
            $this->error('O Telegram exige webhook HTTPS (não aceita http://127.0.0.1).');
            $this->line('Use túnel (ngrok/cloudflared), defina APP_URL=https://... e rode de novo.');
            $this->line('Ou: php artisan telegram:webhook-sync --url=https://seu-tunel.ngrok.app/api/v1/webhooks/telegram');

            return self::FAILURE;
        }

        $payload = [
            'url' => $url,
            'allowed_updates' => ['message', 'edited_message'],
            'drop_pending_updates' => true,
        ];

        $secret = config('financial.integrations.telegram.webhook_secret');
        if ($secret) {
            $payload['secret_token'] = $secret;
        }

        $response = Http::external()->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

        return $this->report($response->json(), "Webhook registrado: {$url}");
    }

    protected function report(?array $body, string $successMessage): int
    {
        if (! ($body['ok'] ?? false)) {
            $this->error($body['description'] ?? 'Falha na API do Telegram');

            return self::FAILURE;
        }

        $this->info($successMessage);
        $this->newLine();
        $this->line(app(PlatformOperationsGuide::class)->consoleAfterWebhook('telegram'));

        return self::SUCCESS;
    }
}
