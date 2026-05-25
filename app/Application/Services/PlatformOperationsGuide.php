<?php

namespace App\Application\Services;

class PlatformOperationsGuide
{
    public function helpIntro(): string
    {
        return "Olá! Sou o assistente financeiro.\n\n".
            "📄 Foto ou PDF de comprovante → confirme com SIM ou NÃO.\n".
            "✏️ Texto livre: Gasto de 500 limpeza apto 001\n\n".
            "⚙️ Sem vários terminais:\n".
            "• /ops — o que o servidor precisa rodar\n".
            "• /comandos — tarefas pelo Telegram\n".
            "• /poll — buscar mensagens (se não usar webhook)\n\n".
            "1. Vincule em ".config('app.url')."/integrations/notifications\n".
            "2. /start neste bot";
    }

    public function operationsStatus(): string
    {
        $lines = ["📋 Status do Financial IQ\n"];

        $lines[] = $this->flagLine('Fila', 'QUEUE_CONNECTION', config('queue.default'));
        $lines[] = $this->flagLine('Telegram sync', 'TELEGRAM_INBOUND_SYNC', $this->boolEnv('TELEGRAM_INBOUND_SYNC', true));
        $lines[] = $this->flagLine('Poll automático', 'TELEGRAM_SCHEDULED_POLL', $this->boolEnv('TELEGRAM_SCHEDULED_POLL', false));
        $lines[] = $this->flagLine('Fila automática', 'TELEGRAM_SCHEDULED_QUEUE', $this->boolEnv('TELEGRAM_SCHEDULED_QUEUE', false));
        $lines[] = $this->flagLine('WhatsApp sync', 'WHATSAPP_INBOUND_SYNC', $this->boolEnv('WHATSAPP_INBOUND_SYNC', true));

        $lines[] = "\n".$this->recommendedProcesses();

        return implode("\n", $lines);
    }

    public function commandsForTelegram(bool $isAdmin): string
    {
        $lines = ["🤖 Comandos no bot (sem terminal aberto)\n"];
        $lines[] = '/ops — status e processos necessários';
        $lines[] = '/poll — 1 ciclo getUpdates (dev sem HTTPS)';
        $lines[] = '/comandos — esta lista + /run';

        if ($isAdmin) {
            $lines[] = '/fila — processa jobs (notifications, default, ocr, ai)';
            foreach (config('financial.integrations.telegram.background_commands', []) as $alias => $def) {
                if ($def['public'] ?? false) {
                    continue;
                }
                $desc = $def['description'] ?? $def['label'] ?? $alias;
                $lines[] = "/run {$alias} — {$desc}";
            }
        } else {
            $lines[] = "\n(run e /fila: admin — TELEGRAM_ADMIN_CHAT_IDS no .env)";
        }

        $lines[] = "\n".$this->scheduledViaEnvHint();

        return implode("\n", $lines);
    }

    public function consoleAfterWebhook(string $channel): string
    {
        $header = $channel === 'whatsapp'
            ? "✅ Webhook WhatsApp (Evolution) registrado.\n\n"
            : "✅ Webhook Telegram registrado.\n\n";

        return $header.$this->plainTextGuide();
    }

    public function plainTextGuide(): string
    {
        $blocks = [
            '═══ Processos (evite vários terminais) ═══',
            '',
            $this->recommendedProcesses(),
            '',
            '── Docker (produção) ──',
            '  docker compose up -d',
            '  (serviços: app, nginx, mysql, redis, horizon, scheduler, evolution-api)',
            '',
            '── Agenda automática (schedule:work) ──',
            ...$this->scheduledTaskLines(),
            '',
            '── Setup único ──',
            ...$this->oneTimeSetupLines(),
            '',
            '── Pelo Telegram (admin) ──',
            '  /ops  /comandos  /poll  /fila  /run daily|alerts|webhook|evolution',
            '',
            $this->scheduledViaEnvHint(),
        ];

        return implode("\n", $blocks);
    }

    public function webCardHtml(): string
    {
        $poll = $this->boolEnv('TELEGRAM_SCHEDULED_POLL', false) ? 'ativo' : 'off';
        $queue = $this->boolEnv('TELEGRAM_SCHEDULED_QUEUE', false) ? 'ativo' : 'off';
        $sync = config('queue.default') === 'sync';

        return <<<HTML
<p class="mb-2"><strong>Em desenvolvimento</strong> — um único processo costuma bastar:</p>
<pre class="bg-light p-2 rounded mb-2"><code>php artisan schedule:work</code></pre>
<p class="small text-muted mb-2">Com <code>TELEGRAM_SCHEDULED_POLL=true</code> e <code>TELEGRAM_SCHEDULED_QUEUE=true</code> no <code>.env</code> (substitui terminais com <code>telegram:poll</code> e <code>queue:listen</code>).</p>
<p class="small mb-2">Agendamento: poll automático <strong>{$poll}</strong>, fila automática <strong>{$queue}</strong>, fila global <code>{$this->e(config('queue.default'))}</code>.</p>
HTML.($sync
            ? '<p class="small text-success mb-0">Com <code>QUEUE_CONNECTION=sync</code>, comprovantes Telegram/WhatsApp processam na hora (webhook ou poll).</p>'
            : '<p class="small mb-0">Produção: <code>docker compose up -d</code> sobe <code>horizon</code> + <code>scheduler</code>. Bot: <code>/ops</code> e <code>/comandos</code>.</p>');
    }

    public function recommendedProcessesPlain(): string
    {
        return str_replace(['`', '*'], '', $this->recommendedProcesses());
    }

    protected function recommendedProcesses(): string
    {
        if (config('queue.default') === 'sync'
            && $this->boolEnv('TELEGRAM_INBOUND_SYNC', true)
            && $this->boolEnv('WHATSAPP_INBOUND_SYNC', true)) {
            $extra = '';
            if ($this->boolEnv('TELEGRAM_SCHEDULED_POLL', false)) {
                $extra = "\n• `schedule:work` — poll Telegram a cada minuto";
            } elseif (! $this->hasHttpsWebhook()) {
                $extra = "\n• `/poll` no bot ou `telegram:poll` — dev sem webhook HTTPS";
            }

            return "✅ Modo leve: webhook/sync ativos — não precisa de queue:listen para comprovantes.{$extra}";
        }

        if ($this->boolEnv('TELEGRAM_SCHEDULED_POLL', false) || $this->boolEnv('TELEGRAM_SCHEDULED_QUEUE', false)) {
            return "▶️ Recomendado: um terminal\nphp artisan schedule:work\n".
                "(substitui poll + queue:listen + cron diário)";
        }

        return "▶️ Recomendado (Docker):\ndocker compose up -d\n".
            "horizon + scheduler já inclusos.\n\n".
            "▶️ *Ou manual:*\n".
            "• `php artisan horizon` (ou queue:listen)\n".
            "• `php artisan schedule:work`\n".
            "• Produção Telegram: `telegram:webhook-sync`";
    }

    /**
     * @return list<string>
     */
    protected function scheduledTaskLines(): array
    {
        return [
            '  financial:daily — diário 06:00',
            '  telegram:poll --once — cada minuto (se TELEGRAM_SCHEDULED_POLL=true)',
            '  queue:work --stop-when-empty — cada minuto (se TELEGRAM_SCHEDULED_QUEUE=true)',
        ];
    }

    /**
     * @return list<string>
     */
    protected function oneTimeSetupLines(): array
    {
        return [
            '  php artisan key:generate',
            '  php artisan migrate --seed',
            '  php artisan telegram:webhook-sync  (HTTPS — produção)',
            '  php artisan evolution:webhook-sync   (WhatsApp Evolution)',
        ];
    }

    protected function scheduledViaEnvHint(): string
    {
        return '💡 .env: TELEGRAM_SCHEDULED_POLL=true + TELEGRAM_SCHEDULED_QUEUE=true → só `schedule:work`. '.
            'Produção: webhook HTTPS dispensa poll.';
    }

    protected function flagLine(string $label, string $env, mixed $value): string
    {
        return "• {$label}: `{$value}` ({$env})";
    }

    protected function boolEnv(string $key, bool $default): string
    {
        $v = env($key, $default);

        return filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'sim' : 'não';
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    protected function hasHttpsWebhook(): bool
    {
        $url = (string) config('app.url');

        return str_starts_with($url, 'https://');
    }
}
