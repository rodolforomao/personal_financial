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
            if (is_string($bundle) && $bundle !== '' && self::isAllowedFile($bundle)) {
                $options['verify'] = $bundle;
            } else {
                foreach ([
                    '/etc/ssl/certs/ca-certificates.crt',
                    '/etc/pki/tls/certs/ca-bundle.crt',
                    '/etc/ssl/cert.pem',
                    '/usr/local/etc/openssl/cert.pem',
                ] as $path) {
                    if (self::isAllowedFile($path)) {
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

    protected static function isAllowedFile(string $path): bool
    {
        $openBaseDir = ini_get('open_basedir');
        if (is_string($openBaseDir) && $openBaseDir !== '' && ! self::isWithinOpenBaseDir($path, $openBaseDir)) {
            return false;
        }

        return is_file($path);
    }

    protected static function isWithinOpenBaseDir(string $path, string $openBaseDir): bool
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        foreach (explode(PATH_SEPARATOR, $openBaseDir) as $allowed) {
            $allowed = rtrim(str_replace('\\', '/', $allowed), '/');
            if ($allowed === '') {
                continue;
            }

            if ($normalized === $allowed || str_starts_with($normalized, $allowed.'/')) {
                return true;
            }
        }

        return false;
    }
}
