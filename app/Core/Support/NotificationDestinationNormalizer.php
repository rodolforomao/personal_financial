<?php

namespace App\Core\Support;

use Illuminate\Support\Str;
use Modules\Integrations\Application\Services\TelegramService;

class NotificationDestinationNormalizer
{
    /**
     * Aceita telefone, @usuario, usuario, link t.me, ou chat_id numérico (grupos podem ser negativos).
     */
    public static function telegram(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        $phone = self::telegramPhone($input);
        if ($phone) {
            return $phone;
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

    public static function telegramPhone(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '' || str_starts_with($input, '@') || str_contains($input, 't.me/') || preg_match('/^-\d+$/', $input)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $input) ?? '';
        $hasPhoneSignal = str_starts_with($input, '+')
            || preg_match('/[\s().-]/', $input)
            || (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true));
        if (! $hasPhoneSignal) {
            return null;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        if (strlen($digits) <= 11 && ! str_starts_with($digits, '55')) {
            $digits = '55'.$digits;
        }

        return 'phone:+'.$digits;
    }

    public static function telegramPhoneDigits(string $destination): ?string
    {
        $phone = self::telegramPhone($destination) ?? (str_starts_with($destination, 'phone:+') ? $destination : null);
        if (! $phone) {
            return null;
        }

        return preg_replace('/\D/', '', $phone) ?: null;
    }

    public static function telegramDisplay(string $destination): string
    {
        $digits = self::telegramPhoneDigits($destination);
        if ($digits && str_starts_with($destination, 'phone:+')) {
            return '+'.$digits;
        }

        return $destination;
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
     * Tenta converter telefone/@usuario em chat_id numérico via API do Telegram (opcional).
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

        $telegram = app(TelegramService::class);
        $discovered = str_starts_with($normalized, 'phone:+')
            ? $telegram->discoverChatIdByPhone($botToken, $normalized)
            : $telegram->discoverChatId($botToken, $normalized);

        return $discovered ?: $normalized;
    }
}
