<?php

namespace App\Console\Concerns;

use App\Core\Support\TelegramInboundGuard;
use App\Core\Support\WebPhpAccessGuard;

trait EnsuresPlatformHealthBeforeWebhook
{
    protected function ensurePlatformHealth(): bool
    {
        if (! $this->ensureWebPhpAccess()) {
            return false;
        }

        return $this->ensureTelegramInbound();
    }

    protected function ensureWebPhpAccess(): bool
    {
        if (! config('financial.platform.auto_ensure_web_php', true)) {
            return true;
        }

        $guard = app(WebPhpAccessGuard::class);
        if (! $guard->needsFix()) {
            return true;
        }

        $this->warn('open_basedir do PHP web bloqueia o Laravel — aplicando correção automática...');

        $result = $guard->applyFix();

        foreach ($result['fixed'] as $pool) {
            $this->line("  ✓ {$pool}");
        }

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if ($guard->needsFix()) {
            $this->error('Corrija manualmente: php artisan platform:ensure-health --fix --probe');

            return false;
        }

        if ($result['reloaded']) {
            $this->line('PHP-FPM recarregado.');
        }

        return true;
    }

    protected function ensureTelegramInbound(): bool
    {
        if (! config('financial.platform.auto_ensure_telegram', true)) {
            return true;
        }

        $guard = app(TelegramInboundGuard::class);
        if (! $guard->isConfigured() || ! $guard->needsFix()) {
            return true;
        }

        $this->warn('Telegram inbound com problemas — aplicando correção automática...');

        foreach ($guard->diagnose() as $issue) {
            if ($issue['fixable']) {
                $this->line("  • [{$issue['code']}] {$issue['message']}");
            }
        }

        $result = $guard->applyFix();

        foreach ($result['fixed'] as $fixed) {
            $this->line("  ✓ {$fixed}");
        }

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if ($guard->needsFix()) {
            $this->error('Corrija manualmente: php artisan platform:ensure-health --fix --probe');

            return false;
        }

        return true;
    }
}
