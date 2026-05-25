<?php

namespace Tests\Unit;

use App\Core\Support\HttpClientOptions;
use Tests\TestCase;

class HttpClientOptionsTest extends TestCase
{
    public function test_external_http_client_forces_ipv4_by_default(): void
    {
        config([
            'financial.http.force_ipv4' => true,
            'financial.http.verify_ssl' => true,
            'financial.http.ca_bundle' => null,
        ]);

        $options = HttpClientOptions::verify();

        $this->assertArrayHasKey('verify', $options);

        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $this->assertSame(CURL_IPRESOLVE_V4, $options['curl'][CURLOPT_IPRESOLVE] ?? null);
        }
    }
}
