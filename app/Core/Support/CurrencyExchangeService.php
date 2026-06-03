<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyExchangeService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const FALLBACK_RATE = 5.0;

    public function usdtToBrl(): float
    {
        return Cache::remember('usdt_brl_rate', self::CACHE_TTL, function () {
            try {
                $response = Http::timeout(5)->get('https://api.binance.com/api/v3/ticker/price', [
                    'symbol' => 'USDTBRL',
                ]);
                $price = (float) ($response->json('price') ?? 0);

                return $price > 0 ? $price : self::FALLBACK_RATE;
            } catch (\Throwable $e) {
                Log::warning('CurrencyExchangeService: failed to fetch USDT/BRL rate', ['error' => $e->getMessage()]);

                return self::FALLBACK_RATE;
            }
        });
    }

    /**
     * @return array{amount_brl: float, rate: float}
     */
    public function convertUsdToBrl(float $usd): array
    {
        $rate = $this->usdtToBrl();

        return [
            'amount_brl' => round($usd * $rate, 2),
            'rate' => $rate,
        ];
    }
}
