<?php

namespace App\Console\Commands;

use App\Core\Support\TelegramInboundGuard;
use App\Core\Support\WebPhpAccessGuard;
use Illuminate\Console\Command;

class PlatformEnsureHealthCommand extends Command
{
    protected $signature = 'platform:ensure-health
                            {--fix : Aplica correções automáticas (open_basedir, webhook Telegram, fila)}
                            {--probe : Testa HTTP /up e webhook Telegram após correção}';

    protected $description = 'Detecta e corrige bloqueios do PHP web (Hestia) e integração Telegram inbound';

    /** @var list<string> */
    protected $aliases = ['platform:ensure-web-access'];

    public function handle(WebPhpAccessGuard $webGuard, TelegramInboundGuard $telegramGuard): int
    {
        $exitCode = self::SUCCESS;

        $exitCode = $this->ensureWeb($webGuard) ?: $exitCode;
        $exitCode = $this->ensureTelegram($telegramGuard) ?: $exitCode;

        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        return $this->probeIfRequested($webGuard, $telegramGuard, self::SUCCESS);
    }

    protected function ensureWeb(WebPhpAccessGuard $guard): int
    {
        $issues = $guard->diagnose();

        if ($issues === []) {
            $this->info('PHP web: open_basedir OK para '.base_path());

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->warn("Pool {$issue['pool']} ({$issue['domain']}) bloqueia:");
            foreach ($issue['missing_paths'] as $path) {
                $this->line("  • {$path}");
            }
        }

        if (! $this->option('fix')) {
            $this->line('Execute com --fix para corrigir open_basedir.');

            return self::FAILURE;
        }

        $result = $guard->applyFix();
        $this->reportFixResult($result, 'PHP-FPM recarregado.');

        if ($guard->needsFix()) {
            $this->error('Ainda há pools bloqueando o Laravel.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function ensureTelegram(TelegramInboundGuard $guard): int
    {
        if (! $guard->isConfigured()) {
            $this->line('Telegram inbound: desabilitado ou sem TELEGRAM_BOT_TOKEN.');

            return self::SUCCESS;
        }

        $issues = $guard->diagnose();
        if ($issues === []) {
            $this->info('Telegram inbound: webhook e fila OK.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $prefix = $issue['fixable'] ? '⚠' : 'ℹ';
            $this->line("  {$prefix} [{$issue['code']}] {$issue['message']}");
        }

        if (! $this->option('fix')) {
            $this->line('Execute com --fix para registrar webhook e recuperar fila.');

            return $guard->needsFix() ? self::FAILURE : self::SUCCESS;
        }

        if (! $guard->needsFix()) {
            return self::SUCCESS;
        }

        $result = $guard->applyFix();
        $this->reportFixResult($result);

        if ($guard->needsFix()) {
            $this->error('Telegram inbound ainda com problemas após correção.');

            return self::FAILURE;
        }

        $this->info('Telegram inbound corrigido.');

        return self::SUCCESS;
    }

    /**
     * @param  array{fixed: list<string>, errors: list<string>, reloaded?: bool}  $result
     */
    protected function reportFixResult(array $result, ?string $reloadMessage = null): void
    {
        foreach ($result['fixed'] as $item) {
            $this->info("Corrigido: {$item}");
        }

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if (($result['reloaded'] ?? false) && $reloadMessage) {
            $this->info($reloadMessage);
        }
    }

    protected function probeIfRequested(
        WebPhpAccessGuard $webGuard,
        TelegramInboundGuard $telegramGuard,
        int $successCode,
    ): int {
        if (! $this->option('probe')) {
            return $successCode;
        }

        $status = $webGuard->probeHealthUrl();
        if ($status === null || $status >= 500) {
            $this->error('Probe /up falhou (HTTP '.($status ?? 'timeout').').');

            return self::FAILURE;
        }

        $this->info("Probe /up OK ({$status}).");

        if (! $telegramGuard->isConfigured()) {
            return $successCode;
        }

        $webhookStatus = $telegramGuard->probeWebhookEndpoint();
        if ($webhookStatus !== 200) {
            $this->error('Probe webhook Telegram falhou (HTTP '.($webhookStatus ?? 'timeout').').');

            return self::FAILURE;
        }

        $this->info("Probe webhook Telegram OK ({$webhookStatus}).");

        return $successCode;
    }
}
