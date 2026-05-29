<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

class TelegramInboundGuard
{
    /** @var list<string> */
    protected array $fixableCodes = [
        'webhook_missing',
        'webhook_url_mismatch',
        'webhook_last_error',
        'webhook_probe_failed',
        'queue_backlog',
        'failed_telegram_jobs',
        'queue_worker_down',
    ];

    public function isConfigured(): bool
    {
        return (bool) config('financial.integrations.telegram.inbound_enabled', true)
            && is_string(config('financial.integrations.telegram.bot_token'))
            && config('financial.integrations.telegram.bot_token') !== '';
    }

    public function expectedWebhookUrl(): ?string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '' || ! str_starts_with($appUrl, 'https://')) {
            return null;
        }

        return $appUrl.'/api/v1/webhooks/telegram';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function webhookInfo(): ?array
    {
        $token = config('financial.integrations.telegram.bot_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $response = Http::external()
                ->timeout(15)
                ->get("https://api.telegram.org/bot{$token}/getWebhookInfo");

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json('result');

            return is_array($result) ? $result : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{code: string, message: string, fixable: bool}>
     */
    public function diagnose(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $issues = [];
        $expected = $this->expectedWebhookUrl();

        if ($expected === null) {
            return [[
                'code' => 'webhook_https_required',
                'message' => 'APP_URL precisa ser HTTPS para registrar webhook Telegram.',
                'fixable' => false,
            ]];
        }

        $info = $this->webhookInfo();
        if ($info === null) {
            return [[
                'code' => 'webhook_info_unreachable',
                'message' => 'Não foi possível consultar getWebhookInfo na API do Telegram.',
                'fixable' => true,
            ]];
        }

        $current = rtrim((string) ($info['url'] ?? ''), '/');
        if ($current === '') {
            $issues[] = [
                'code' => 'webhook_missing',
                'message' => 'Webhook não registrado no Telegram.',
                'fixable' => true,
            ];
        } elseif (! $this->urlsMatch($current, $expected)) {
            $issues[] = [
                'code' => 'webhook_url_mismatch',
                'message' => "Webhook aponta para {$current}, esperado {$expected}.",
                'fixable' => true,
            ];
        }

        $lastError = trim((string) ($info['last_error_message'] ?? ''));
        if ($lastError !== '') {
            $issues[] = [
                'code' => 'webhook_last_error',
                'message' => $lastError,
                'fixable' => true,
            ];
        }

        $probe = $this->probeWebhookEndpoint();
        if ($probe !== 200) {
            $issues[] = [
                'code' => 'webhook_probe_failed',
                'message' => 'Probe HTTP local em /api/v1/webhooks/telegram retornou '.($probe ?? 'timeout').'.',
                'fixable' => true,
            ];
        }

        if (! $this->inboundSync()) {
            $pending = $this->pendingNotificationJobs();
            $workerDown = ! $this->queueWorkerRunning();

            if ($workerDown) {
                $issues[] = [
                    'code' => 'queue_worker_down',
                    'message' => 'Worker da fila (financial-queue) não está ativo.',
                    'fixable' => true,
                ];

                if ($pending > 0) {
                    $issues[] = [
                        'code' => 'queue_backlog',
                        'message' => "Fila notifications com {$pending} job(s) pendente(s) e worker parado.",
                        'fixable' => true,
                    ];
                }
            }

            $failed = $this->failedTelegramJobsCount();
            if ($failed > 0) {
                $issues[] = [
                    'code' => 'failed_telegram_jobs',
                    'message' => "{$failed} job(s) Telegram falharam na fila.",
                    'fixable' => true,
                ];
            }
        }

        return $issues;
    }

    public function needsFix(): bool
    {
        foreach ($this->diagnose() as $issue) {
            if ($issue['fixable'] && in_array($issue['code'], $this->fixableCodes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{fixed: list<string>, errors: list<string>}
     */
    public function applyFix(): array
    {
        $result = ['fixed' => [], 'errors' => []];

        if (! $this->isConfigured()) {
            return $result;
        }

        $codes = array_column($this->diagnose(), 'code');

        if ($this->shouldRegisterWebhook($codes)) {
            $registered = $this->registerWebhook(dropPendingUpdates: false);
            if ($registered['ok'] ?? false) {
                $result['fixed'][] = 'webhook_registered';
            } else {
                $result['errors'][] = $registered['error'] ?? 'Falha ao registrar webhook Telegram.';
            }
        }

        if (! $this->inboundSync()) {
            if (in_array('queue_worker_down', $codes, true) || in_array('queue_backlog', $codes, true)) {
                if ($this->restartQueueWorker()) {
                    $result['fixed'][] = 'queue_worker_restarted';
                } elseif (in_array('queue_worker_down', $codes, true)) {
                    $result['errors'][] = 'Não foi possível reiniciar financial-queue (precisa root/systemd).';
                }
            }

            if (in_array('failed_telegram_jobs', $codes, true)) {
                $retried = $this->retryFailedTelegramJobs();
                if ($retried > 0) {
                    $result['fixed'][] = "failed_jobs_retried:{$retried}";
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $codes
     */
    protected function shouldRegisterWebhook(array $codes): bool
    {
        return array_intersect($codes, [
            'webhook_missing',
            'webhook_url_mismatch',
            'webhook_last_error',
            'webhook_probe_failed',
            'webhook_info_unreachable',
        ]) !== [];
    }

    /**
     * @return array{ok: bool, error?: string, url?: string}
     */
    public function registerWebhook(?string $url = null, bool $dropPendingUpdates = false): array
    {
        $token = config('financial.integrations.telegram.bot_token');
        $target = $url ?? $this->expectedWebhookUrl();

        if (! is_string($token) || $token === '') {
            return ['ok' => false, 'error' => 'TELEGRAM_BOT_TOKEN ausente.'];
        }

        if (! is_string($target) || ! str_starts_with($target, 'https://')) {
            return ['ok' => false, 'error' => 'URL do webhook precisa ser HTTPS.'];
        }

        $payload = [
            'url' => $target,
            'allowed_updates' => ['message', 'edited_message'],
            'drop_pending_updates' => $dropPendingUpdates,
        ];

        $secret = config('financial.integrations.telegram.webhook_secret');
        if (is_string($secret) && $secret !== '') {
            $payload['secret_token'] = $secret;
        }

        try {
            $response = Http::external()
                ->timeout(15)
                ->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! ($response->json('ok') ?? false)) {
            return [
                'ok' => false,
                'error' => (string) ($response->json('description') ?? $response->body()),
            ];
        }

        return ['ok' => true, 'url' => $target];
    }

    public function probeWebhookEndpoint(): ?int
    {
        $url = $this->expectedWebhookUrl();
        if ($url === null) {
            return null;
        }

        $headers = ['Content-Type' => 'application/json'];
        $secret = config('financial.integrations.telegram.webhook_secret');
        if (is_string($secret) && $secret !== '') {
            $headers['X-Telegram-Bot-Api-Secret-Token'] = $secret;
        }

        try {
            return Http::timeout(10)
                ->withHeaders($headers)
                ->post($url, [
                    'update_id' => 0,
                    'message' => [
                        'message_id' => 0,
                        'from' => ['id' => 0, 'is_bot' => false],
                        'chat' => ['id' => 0, 'type' => 'private'],
                        'date' => 0,
                        'text' => '/healthcheck',
                    ],
                ])
                ->status();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function inboundSync(): bool
    {
        return (bool) config('financial.integrations.telegram.inbound_sync', true);
    }

    protected function pendingNotificationJobs(): int
    {
        try {
            return (int) Queue::size('notifications');
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function failedTelegramJobsCount(): int
    {
        try {
            return (int) DB::table('failed_jobs')
                ->where('queue', 'notifications')
                ->where('payload', 'like', '%ProcessTelegramUpdateJob%')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function retryFailedTelegramJobs(): int
    {
        try {
            $uuids = DB::table('failed_jobs')
                ->where('queue', 'notifications')
                ->where('payload', 'like', '%ProcessTelegramUpdateJob%')
                ->orderByDesc('failed_at')
                ->limit(20)
                ->pluck('uuid');
        } catch (\Throwable) {
            return 0;
        }

        $retried = 0;
        foreach ($uuids as $uuid) {
            try {
                Artisan::call('queue:retry', ['id' => $uuid]);
                $retried++;
            } catch (\Throwable) {
                continue;
            }
        }

        return $retried;
    }

    protected function queueWorkerRunning(): bool
    {
        $check = Process::run(['systemctl', 'is-active', '--quiet', 'financial-queue']);

        return $check->successful();
    }

    protected function restartQueueWorker(): bool
    {
        if (! function_exists('posix_getuid') || posix_getuid() !== 0) {
            return false;
        }

        $restart = Process::run(['systemctl', 'restart', 'financial-queue']);

        return $restart->successful();
    }

    protected function urlsMatch(string $current, string $expected): bool
    {
        return rtrim($current, '/') === rtrim($expected, '/');
    }
}
