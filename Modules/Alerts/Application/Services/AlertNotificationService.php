<?php

namespace Modules\Alerts\Application\Services;

use App\Core\Support\IntegrationCredentialsResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Alerts\Infrastructure\Models\Alert;
use Modules\Alerts\Infrastructure\Models\AlertChannel;
use Modules\Integrations\Application\Services\TelegramService;
use Modules\Integrations\Application\Services\WhatsAppService;

class AlertNotificationService
{
    public function __construct(
        protected IntegrationCredentialsResolver $credentials,
    ) {}

    public function dispatch(Alert $alert): void
    {
        $channels = AlertChannel::query()
            ->where('workspace_id', $alert->workspace_id)
            ->where('is_active', true)
            ->get();

        foreach ($channels as $channel) {
            $userId = $channel->user_id;

            match ($channel->channel) {
                'email' => Mail::raw($alert->message, fn ($m) => $m->to($channel->destination)->subject($alert->title)),
                'telegram' => $this->sendTelegram($userId, (int) $alert->workspace_id, $channel->destination, $alert),
                'whatsapp' => $this->sendWhatsApp($userId, (int) $alert->workspace_id, $channel->destination, $alert),
                default => Log::info('Alert channel not configured', ['channel' => $channel->channel]),
            };
        }

        $alert->update(['is_sent' => true]);
    }

    protected function sendTelegram(?int $userId, int $workspaceId, string $destination, Alert $alert): void
    {
        $config = $this->credentials->telegram($userId, $workspaceId);
        if (! $config) {
            $config = [
                'bot_token' => config('financial.integrations.telegram.bot_token'),
                'chat_id' => $destination,
                'source' => 'legacy',
            ];
        }

        if (empty($config['bot_token'])) {
            return;
        }

        app(TelegramService::class)->sendWithConfig([
            'chat_id' => $destination,
            'bot_token' => $config['bot_token'],
        ], "{$alert->title}\n{$alert->message}");
    }

    protected function sendWhatsApp(?int $userId, int $workspaceId, string $destination, Alert $alert): void
    {
        $config = $this->credentials->whatsapp($userId, $workspaceId);
        if (! $config) {
            return;
        }

        if (($config['provider'] ?? 'http') === 'http'
            && (empty($config['api_url']) || empty($config['token']))) {
            return;
        }

        app(WhatsAppService::class)->sendWithConfig(
            array_merge($config, ['phone' => $destination]),
            "{$alert->title}\n{$alert->message}"
        );
    }
}
