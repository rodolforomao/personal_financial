<?php

namespace App\Core\Support;

use Illuminate\Support\Str;

class NotificationDestinationNormalizer
{
    /**
     * Aceita @usuario, usuario, link t.me, ou chat_id numérico (grupos podem ser negativos).
     */
    public static function telegram(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (preg_match('#(?:https?://)?(?:www\.)?t\.me/([a-zA-Z0-9_]{4,32})/?#i', $input, $m)) {
            return '@'.$m[1];
        }

        if (str_starts_with($input, '@')) {
            $user = ltrim($input, '@');

            return $user !== '' ? '@'.Str::lower($user) : null;
        }

        if (preg_match('/^-?\d+$/', $input)) {
            return $input;
        }

        if (preg_match('/^[a-zA-Z0-9_]{4,32}$/i', $input)) {
            return '@'.Str::lower($input);
        }

        return null;
    }

    /**
     * Aceita +55 (11) 99999-9999, 5511999999999, etc. Retorna só dígitos com DDI.
     */
    public static function whatsapp(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $input) ?? '';

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        if (strlen($digits) <= 11 && ! str_starts_with($digits, '55')) {
            $digits = '55'.$digits;
        }

        return $digits;
    }

    /**
     * Tenta converter @usuario em chat_id numérico via API do Telegram (opcional).
     */
    public static function resolveTelegramChatId(string $destination, ?string $botToken): string
    {
        $normalized = self::telegram($destination) ?? $destination;

        if (! $botToken) {
            return $normalized;
        }

        if (preg_match('/^-?\d+$/', $normalized)) {
            return $normalized;
        }

        $discovered = app(\Modules\Integrations\Application\Services\TelegramService::class)
            ->discoverChatId($botToken, $normalized);

        return $discovered ?: $normalized;
    }
}
