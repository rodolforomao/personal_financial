<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Integrations\Application\Services\TelegramPollService;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll
                            {--once : Processa um lote e encerra (útil para testes ou cron)}
                            {--timeout=25 : Long polling timeout em segundos (máx. 50)}';

    protected $description = 'Recebe mensagens do Telegram via getUpdates (dev local — sem HTTPS/ngrok)';

    public function handle(TelegramPollService $poll): int
    {
        $token = config('financial.integrations.telegram.bot_token');
        if (! $token) {
            $this->error('Defina TELEGRAM_BOT_TOKEN no .env');

            return self::FAILURE;
        }

        if (! config('financial.integrations.telegram.inbound_enabled', true)) {
            $this->warn('TELEGRAM_INBOUND_ENABLED=false — ative no .env para processar mensagens.');

            return self::FAILURE;
        }

        if ($this->option('once')) {
            $result = $poll->pollOnce((int) $this->option('timeout'));
            if (! ($result['ok'] ?? false)) {
                $this->error($result['error'] ?? 'Falha no poll');

                return self::FAILURE;
            }
            foreach ($result['lines'] as $line) {
                $this->line($line);
            }
            $this->info("Concluído: {$result['processed']} update(s), {$result['handled']} processado(s).");

            return self::SUCCESS;
        }

        $this->info('Polling Telegram (Ctrl+C para parar).');
        $this->line('Sem terminal: TELEGRAM_SCHEDULED_POLL=true ou /poll no bot (fila + cron).');
        $this->line('Produção: php artisan telegram:webhook-sync com URL HTTPS pública.');

        do {
            $result = $poll->pollOnce((int) $this->option('timeout'));
            if (! ($result['ok'] ?? false)) {
                $this->error($result['error'] ?? 'Falha no poll');

                return self::FAILURE;
            }
            foreach ($result['lines'] as $line) {
                $this->line($line);
            }
        } while (true);
    }
}
