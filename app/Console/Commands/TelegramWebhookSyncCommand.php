<?php

namespace App\Console\Commands;

use App\Application\Services\PlatformOperationsGuide;
use App\Console\Concerns\EnsuresPlatformHealthBeforeWebhook;
use App\Core\Support\TelegramInboundGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramWebhookSyncCommand extends Command
{
    use EnsuresPlatformHealthBeforeWebhook;

    protected $signature = 'telegram:webhook-sync
                            {--url= : URL pública do webhook (padrão: APP_URL/api/v1/webhooks/telegram)}
                            {--drop : Remove o webhook do Telegram}';

    protected $description = 'Registra (ou remove) o webhook do bot Telegram para receber mensagens e criar lançamentos';

    public function handle(TelegramInboundGuard $telegram): int
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
            ?? $telegram->expectedWebhookUrl();

        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            $this->error('O Telegram exige webhook HTTPS (não aceita http://127.0.0.1).');
            $this->line('Use túnel (ngrok/cloudflared), defina APP_URL=https://... e rode de novo.');
            $this->line('Ou: php artisan telegram:webhook-sync --url=https://seu-tunel.ngrok.app/api/v1/webhooks/telegram');

            return self::FAILURE;
        }

        if (! $this->ensurePlatformHealth()) {
            return self::FAILURE;
        }

        $registered = $telegram->registerWebhook($url, dropPendingUpdates: true);

        return $this->report($registered, "Webhook registrado: {$url}");
    }

    /**
     * @param  array{ok: bool, error?: string, url?: string}  $registered
     */
    protected function report(array $registered, string $successMessage): int
    {
        if (! ($registered['ok'] ?? false)) {
            $this->error($registered['error'] ?? 'Falha na API do Telegram');

            return self::FAILURE;
        }

        $this->info($successMessage);
        $this->newLine();
        $this->line(app(PlatformOperationsGuide::class)->consoleAfterWebhook('telegram'));

        return self::SUCCESS;
    }
}
