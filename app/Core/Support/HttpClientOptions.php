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
        $options = [];

        if ($configured === false || $configured === 'false' || $configured === '0') {
            $options['verify'] = false;
        } else {
            $bundle = config('financial.http.ca_bundle');
            if (is_string($bundle) && $bundle !== '' && is_file($bundle)) {
                $options['verify'] = $bundle;
            } else {
                foreach ([
                    '/etc/ssl/certs/ca-certificates.crt',
                    '/etc/pki/tls/certs/ca-bundle.crt',
                    '/etc/ssl/cert.pem',
                    '/usr/local/etc/openssl/cert.pem',
                ] as $path) {
                    if (is_file($path)) {
                        $options['verify'] = $path;
                        break;
                    }
                }
            }
        }

        $options['verify'] ??= true;

        if (config('financial.http.force_ipv4', true) && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $options['curl'][CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        return $options;
    }
}
