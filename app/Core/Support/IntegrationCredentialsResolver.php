<?php

namespace App\Core\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class IntegrationCredentialsResolver
{
    public function telegram(?int $userId, int $workspaceId): ?array
    {
        $prefs = $this->notificationPrefs($userId);
        $mode = $prefs['telegram_mode'] ?? 'system';
        $chatId = $prefs['telegram_chat_id'] ?? null;

        if (empty($chatId)) {
            return null;
        }

        if ($mode === 'own') {
            $token = $this->decrypt($prefs['telegram_bot_token_enc'] ?? null);
            if ($token) {
                return [
                    'bot_token' => $token,
                    'chat_id' => $chatId,
                    'source' => 'user',
                ];
            }
        }

        $systemToken = config('financial.integrations.telegram.bot_token');
        if (! empty($systemToken) && config('financial.integrations.system_enabled', true)) {
            return [
                'bot_token' => $systemToken,
                'chat_id' => $chatId,
                'source' => 'system',
            ];
        }

        return null;
    }

    public function whatsapp(?int $userId, int $workspaceId): ?array
    {
        $prefs = $this->notificationPrefs($userId);
        $mode = $prefs['whatsapp_mode'] ?? 'system';
        $phone = $prefs['whatsapp_phone'] ?? null;

        if (empty($phone)) {
            return null;
        }

        if ($mode === 'own') {
            $url = $this->decrypt($prefs['whatsapp_api_url_enc'] ?? null)
                ?: config('financial.integrations.whatsapp.api_url');
            $token = $this->decrypt($prefs['whatsapp_api_token_enc'] ?? null);
            if ($url && $token) {
                return [
                    'api_url' => $url,
                    'token' => $token,
                    'phone' => $phone,
                    'source' => 'user',
                ];
            }
        }

        if (config('financial.integrations.system_enabled', true)) {
            if ($this->systemUsesEvolution()) {
                return [
                    'phone' => $phone,
                    'provider' => 'evolution',
                    'source' => 'system',
                ];
            }

            $systemUrl = config('financial.integrations.whatsapp.api_url');
            $systemToken = config('financial.integrations.whatsapp.token');
            if (! empty($systemUrl) && ! empty($systemToken)) {
                return [
                    'api_url' => $systemUrl,
                    'token' => $systemToken,
                    'phone' => $phone,
                    'provider' => 'http',
                    'source' => 'system',
                ];
            }
        }

        return null;
    }

    public function status(?int $userId): array
    {
        $prefs = $this->notificationPrefs($userId);

        $telegramReady = $this->telegram($userId, 0) !== null;
        $whatsappReady = $this->whatsapp($userId, 0) !== null;

        return [
            'telegram_mode' => $prefs['telegram_mode'] ?? 'system',
            'telegram_chat_id' => $prefs['telegram_chat_id'] ?? '',
            'telegram_ready' => $telegramReady,
            'telegram_system' => ! empty(config('financial.integrations.telegram.bot_token')),
            'telegram_user_key' => ! empty($this->decrypt($prefs['telegram_bot_token_enc'] ?? null)),
            'whatsapp_mode' => $prefs['whatsapp_mode'] ?? 'system',
            'whatsapp_phone' => $prefs['whatsapp_phone'] ?? '',
            'whatsapp_ready' => $whatsappReady,
            'whatsapp_system' => $this->systemWhatsAppReady(),
            'whatsapp_provider' => config('financial.integrations.whatsapp.provider', 'evolution'),
            'whatsapp_user_key' => ! empty($this->decrypt($prefs['whatsapp_api_token_enc'] ?? null)),
            'notify_telegram' => (bool) ($prefs['notify_telegram'] ?? false),
            'notify_whatsapp' => (bool) ($prefs['notify_whatsapp'] ?? false),
            'inbound_ai_enabled' => (bool) ($prefs['inbound_ai_enabled'] ?? false),
        ];
    }

    protected function notificationPrefs(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

        return User::query()->find($userId)?->preferences['notifications'] ?? [];
    }

    protected function systemUsesEvolution(): bool
    {
        return config('financial.integrations.whatsapp.provider') === 'evolution'
            && ! empty(config('financial.integrations.evolution.api_key'))
            && ! empty(config('financial.integrations.evolution.instance_name'));
    }

    protected function systemWhatsAppReady(): bool
    {
        if (! config('financial.integrations.system_enabled', true)) {
            return false;
        }

        if ($this->systemUsesEvolution()) {
            return ! empty(config('financial.integrations.evolution.api_url'));
        }

        return ! empty(config('financial.integrations.whatsapp.api_url'))
            && ! empty(config('financial.integrations.whatsapp.token'));
    }

    protected function decrypt(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
