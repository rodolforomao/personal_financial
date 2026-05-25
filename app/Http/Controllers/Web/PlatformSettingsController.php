<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformSettingsController extends Controller
{
    private const LIQUIDX_PREFIX = 'financial.billing.liquidx.';

    /** @var array<string, mixed> */
    private const LIQUIDX_DEFAULTS = [
        'base_url' => 'https://liquidx.pro/api',
        'api_key' => null,
        'integrated_code' => null,
        'integrated_payment_path' => '/integrated-payment',
        'integrated_payment_status_path' => '/integrated-payment/status',
        'thirdwallet' => null,
        'webhook_secret' => null,
        'timeout' => 20,
        'default_payer_phone' => null,
    ];

    public function edit(PlatformSettings $settings): View
    {
        $liquidx = [];

        foreach (array_keys(self::LIQUIDX_DEFAULTS) as $field) {
            $liquidx[$field] = $this->liquidxValue($settings, $field);
            $liquidx[$field.'_from_database'] = $settings->has($this->liquidxKey($field));
        }

        $liquidx['configured'] = filled($liquidx['base_url']) && filled($liquidx['integrated_code']);
        $liquidx['has_api_key'] = filled($liquidx['api_key']);
        $liquidx['has_webhook_secret'] = filled($liquidx['webhook_secret']);
        $liquidx['webhook_url'] = route('webhooks.liquidx.payments');

        return view('admin.settings.edit', [
            'liquidx' => $liquidx,
        ]);
    }

    public function update(Request $request, PlatformSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'base_url' => 'required|url|max:500',
            'integrated_code' => 'required|string|max:255',
            'integrated_payment_path' => 'required|string|max:120|starts_with:/',
            'integrated_payment_status_path' => 'required|string|max:120|starts_with:/',
            'thirdwallet' => 'nullable|string|max:255',
            'default_payer_phone' => 'nullable|string|max:30',
            'timeout' => 'required|integer|min:1|max:120',
            'api_key' => 'nullable|string|max:500',
            'webhook_secret' => 'nullable|string|max:500',
            'clear_api_key' => 'nullable|boolean',
            'clear_webhook_secret' => 'nullable|boolean',
        ]);

        $settings->put($this->liquidxKey('base_url'), rtrim(trim($validated['base_url']), '/'));
        $settings->put($this->liquidxKey('integrated_code'), trim($validated['integrated_code']));
        $settings->put($this->liquidxKey('integrated_payment_path'), '/'.ltrim(trim($validated['integrated_payment_path']), '/'));
        $settings->put($this->liquidxKey('integrated_payment_status_path'), '/'.ltrim(trim($validated['integrated_payment_status_path']), '/'));
        $settings->put($this->liquidxKey('thirdwallet'), filled($validated['thirdwallet'] ?? null) ? trim($validated['thirdwallet']) : '');
        $settings->put($this->liquidxKey('default_payer_phone'), filled($validated['default_payer_phone'] ?? null) ? trim($validated['default_payer_phone']) : '');
        $settings->put($this->liquidxKey('timeout'), (string) $validated['timeout']);

        if ($request->boolean('clear_api_key')) {
            $settings->put($this->liquidxKey('api_key'), '');
        } elseif ($request->filled('api_key')) {
            $settings->put($this->liquidxKey('api_key'), trim($validated['api_key']));
        }

        if ($request->boolean('clear_webhook_secret')) {
            $settings->put($this->liquidxKey('webhook_secret'), '');
        } elseif ($request->filled('webhook_secret')) {
            $settings->put($this->liquidxKey('webhook_secret'), trim($validated['webhook_secret']));
        }

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Configuração Liquidx.pro salva.');
    }

    private function liquidxValue(PlatformSettings $settings, string $field): mixed
    {
        return $settings->get(
            $this->liquidxKey($field),
            config($this->liquidxKey($field), self::LIQUIDX_DEFAULTS[$field] ?? null),
        );
    }

    private function liquidxKey(string $field): string
    {
        return self::LIQUIDX_PREFIX.$field;
    }
}
