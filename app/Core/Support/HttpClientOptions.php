<?php

namespace App\Core\Support;

class HttpClientOptions
{
    /**
     * Opções Guzzle/cURL para APIs externas (Telegram, OpenAI, etc.).
     *
     * @return array<string, mixed>
     */
    public static function verify(): array
    {
        $configured = config('financial.http.verify_ssl', true);

        if ($configured === false || $configured === 'false' || $configured === '0') {
            return ['verify' => false];
        }

        $bundle = config('financial.http.ca_bundle');
        if (is_string($bundle) && $bundle !== '' && is_file($bundle)) {
            return ['verify' => $bundle];
        }

        foreach ([
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
            '/usr/local/etc/openssl/cert.pem',
        ] as $path) {
            if (is_file($path)) {
                return ['verify' => $path];
            }
        }

        return ['verify' => true];
    }
}
