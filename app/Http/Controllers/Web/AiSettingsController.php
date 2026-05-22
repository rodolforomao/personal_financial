<?php

namespace App\Http\Controllers\Web;

use App\Core\Support\AiModelCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Intelligence\Application\Services\AiCredentialsResolver;

class AiSettingsController extends Controller
{
    public function edit(Request $request, AiCredentialsResolver $resolver): View
    {
        $user = $request->user();
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $aiPrefs = $user->preferences['ai'] ?? [];
        $provider = $aiPrefs['provider'] ?? config('financial.ai.default', 'openai');

        return view('ai.settings', [
            'status' => $resolver->status($user->id, $workspaceId),
            'prefs' => $aiPrefs,
            'providers' => [
                'openai' => 'OpenAI',
                'openrouter' => 'OpenRouter',
            ],
            'modelsByProvider' => AiModelCatalog::allGrouped(),
            'selectedModel' => AiModelCatalog::resolveModel($provider, $aiPrefs['model'] ?? null),
            'selectedProvider' => $provider,
            'modelHints' => collect(AiModelCatalog::allGrouped())
                ->map(fn ($models) => collect($models)->mapWithKeys(fn ($m, $id) => [$id => $m['hint'] ?? '']))
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $provider = $request->input('provider', config('financial.ai.default', 'openai'));
        $user = $request->user();

        $rules = [
            'mode' => 'required|in:system,own',
            'provider' => 'required|in:openai,openrouter',
            'api_key' => 'nullable|string|max:500',
            'model' => [
                'required',
                'string',
                'max:120',
                Rule::in(array_keys(AiModelCatalog::forProvider($provider))),
            ],
        ];

        $alreadyAccepted = ! empty(($user->preferences['ai'] ?? [])['platform_ai_accepted_at']);
        if ($request->input('mode') === 'system' && ! $user->is_platform_internal && ! $alreadyAccepted) {
            $rules['accept_platform_billing'] = 'accepted';
        }

        $validated = $request->validate($rules);

        $prefs = $user->preferences ?? [];
        $ai = $prefs['ai'] ?? [];

        $ai['mode'] = $validated['mode'];
        $ai['provider'] = $validated['provider'];
        $ai['model'] = $validated['model'];

        if ($validated['mode'] === 'system' && ! $user->is_platform_internal) {
            $ai['platform_ai_accepted_at'] = $ai['platform_ai_accepted_at'] ?? now()->toIso8601String();
        }

        if ($validated['mode'] === 'own' && ! empty($validated['api_key'])) {
            $keyField = $validated['provider'].'_api_key_enc';
            $ai[$keyField] = \Illuminate\Support\Facades\Crypt::encryptString(trim($validated['api_key']));
        }

        $prefs['ai'] = $ai;
        $user->forceFill(['preferences' => $prefs])->save();

        $label = AiModelCatalog::label($validated['provider'], $validated['model']);

        return redirect()->route('ai.settings')->with('success', "Configuração salva — modelo: {$label}.");
    }

    public function removeKey(Request $request): RedirectResponse
    {
        $user = $request->user();
        $prefs = $user->preferences ?? [];
        $provider = $request->input('provider', config('financial.ai.default', 'openai'));

        if (isset($prefs['ai'][$provider.'_api_key_enc'])) {
            unset($prefs['ai'][$provider.'_api_key_enc']);
            $user->forceFill(['preferences' => $prefs])->save();
        }

        return redirect()->route('ai.settings')->with('success', 'API key pessoal removida.');
    }
}
