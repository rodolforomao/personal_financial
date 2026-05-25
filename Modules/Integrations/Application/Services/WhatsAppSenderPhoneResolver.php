<?php

namespace Modules\Integrations\Application\Services;

use App\Core\Support\NotificationDestinationNormalizer;
use App\Models\User;

class WhatsAppSenderPhoneResolver
{
    /**
     * Extrai o telefone do remetente a partir do payload Evolution/Baileys.
     * WhatsApp Business pode enviar remoteJid como NNN@lid em vez de @s.whatsapp.net.
     */
    public function resolve(array $key, array $data, array $payload): ?string
    {
        foreach ($this->jidCandidates($key, $data, $payload) as $jid) {
            $phone = $this->phoneFromJid($jid);
            if ($phone !== null) {
                return $phone;
            }
        }

        if ($this->isLidJid((string) ($key['remoteJid'] ?? ''))) {
            return $this->soleLinkedWhatsAppPhone();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function jidCandidates(array $key, array $data, array $payload): array
    {
        $candidates = [
            (string) ($key['remoteJidAlt'] ?? ''),
            (string) ($key['participant'] ?? ''),
            (string) (data_get($data, 'senderPn') ?? ''),
            (string) (data_get($data, 'participant') ?? ''),
            (string) (data_get($payload, 'senderPn') ?? ''),
            (string) ($key['remoteJid'] ?? ''),
        ];

        return array_values(array_filter(array_unique($candidates)));
    }

    protected function phoneFromJid(string $jid): ?string
    {
        if ($jid === '' || str_contains($jid, '@g.us')) {
            return null;
        }

        if ($this->isLidJid($jid)) {
            return null;
        }

        if (! str_contains($jid, '@')) {
            $digits = preg_replace('/\D/', '', $jid) ?? '';

            return strlen($digits) >= 10 ? $digits : null;
        }

        if (! str_contains($jid, '@s.whatsapp.net')) {
            return null;
        }

        $phone = preg_replace('/\D/', '', str_replace('@s.whatsapp.net', '', $jid)) ?? '';

        return strlen($phone) >= 10 ? $phone : null;
    }

    protected function isLidJid(string $jid): bool
    {
        return str_contains(strtolower($jid), '@lid');
    }

    protected function soleLinkedWhatsAppPhone(): ?string
    {
        $phones = [];
        foreach (User::query()->get() as $user) {
            $stored = NotificationDestinationNormalizer::whatsapp(
                (string) ($user->preferences['notifications']['whatsapp_phone'] ?? '')
            );
            if ($stored) {
                $phones[$stored] = true;
            }
        }

        if (count($phones) !== 1) {
            return null;
        }

        return array_key_first($phones);
    }
}
