<?php

namespace Modules\Intelligence\Application\Services;

use App\Core\DTOs\AiCredentials;
use App\Core\Exceptions\AiUnavailableException;
use App\Core\Support\AiModelCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Infrastructure\Models\Workspace;

class AiCredentialsResolver
{
    public function resolve(?int $userId, int $workspaceId): AiCredentials
    {
        $user = $userId ? User::query()->find($userId) : null;
        $aiPrefs = $user?->preferences['ai'] ?? [];
        $mode = $aiPrefs['mode'] ?? 'system';
        $provider = $aiPrefs['provider'] ?? config('financial.ai.default', 'openai');
        $model = AiModelCatalog::resolveModel($provider, $aiPrefs['model'] ?? null);

        if ($mode === 'own') {
            $userKey = $this->decryptUserKey($aiPrefs, $provider);
            if ($userKey) {
                return new AiCredentials(
                    provider: $provider,
                    apiKey: $userKey,
                    model: $model,
                    source: 'user',
                    isBillable: false,
                );
            }
        }

        if ($mode === 'system') {
            if (! $this->userMayUsePlatformAi($user, $aiPrefs)) {
                throw AiUnavailableException::platformBillingNotAccepted();
            }

            if ($this->systemAllowsWorkspace($workspaceId)) {
                $systemKey = config("financial.ai.{$provider}.api_key");
                if (! empty($systemKey)) {
                    $billable = config('financial.ai.platform_billing_enabled', true)
                        && $user
                        && ! $user->is_platform_internal;

                    return new AiCredentials(
                        provider: $provider,
                        apiKey: $systemKey,
                        model: $model,
                        source: $user?->is_platform_internal ? 'system_internal' : 'system',
                        isBillable: $billable,
                    );
                }
            }
        }

        throw AiUnavailableException::notConfigured();
    }

    public function status(?int $userId, int $workspaceId): array
    {
        $user = $userId ? User::query()->find($userId) : null;
        $aiPrefs = $user?->preferences['ai'] ?? [];
        $provider = $aiPrefs['provider'] ?? config('financial.ai.default', 'openai');

        $systemKey = config("financial.ai.{$provider}.api_key");
        $userHasKey = ! empty($this->decryptUserKey($aiPrefs, $provider));
        $isInternal = (bool) ($user?->is_platform_internal ?? false);
        $platformAccepted = $this->hasAcceptedPlatformBilling($aiPrefs);
        $mayUsePlatform = $this->userMayUsePlatformAi($user, $aiPrefs);

        $selectedModel = AiModelCatalog::resolveModel($provider, $aiPrefs['model'] ?? null);

        try {
            $active = $this->resolve($userId, $workspaceId);
            $ready = true;
            $activeSource = $active->source;
            $activeModel = $active->model;
            $isBillable = $active->isBillable;
        } catch (AiUnavailableException $e) {
            $ready = false;
            $activeSource = null;
            $activeModel = $selectedModel;
            $isBillable = false;
            $blockedReason = $e->getMessage();
        }

        return [
            'mode' => $aiPrefs['mode'] ?? 'system',
            'provider' => $provider,
            'model' => $activeModel,
            'model_label' => AiModelCatalog::label($provider, $activeModel),
            'user_has_key' => $userHasKey,
            'system_available' => ! empty($systemKey) && $this->systemAllowsWorkspace($workspaceId),
            'ready' => $ready,
            'active_source' => $activeSource,
            'is_platform_internal' => $isInternal,
            'platform_ai_accepted' => $platformAccepted,
            'may_use_platform_ai' => $mayUsePlatform,
            'is_billable' => $isBillable ?? false,
            'blocked_reason' => $blockedReason ?? null,
        ];
    }

    public function userMayUsePlatformAi(?User $user, ?array $aiPrefs = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_platform_internal) {
            return true;
        }

        $prefs = $aiPrefs ?? ($user->preferences['ai'] ?? []);

        return $this->hasAcceptedPlatformBilling($prefs);
    }

    protected function hasAcceptedPlatformBilling(array $aiPrefs): bool
    {
        return ! empty($aiPrefs['platform_ai_accepted_at']);
    }

    protected function systemAllowsWorkspace(int $workspaceId): bool
    {
        if (! config('financial.ai.system_enabled', true)) {
            return false;
        }

        $workspace = Workspace::query()->find($workspaceId);

        return ($workspace?->settings['ai']['allow_system'] ?? true) !== false;
    }

    protected function decryptUserKey(array $aiPrefs, string $provider): ?string
    {
        $encrypted = $aiPrefs["{$provider}_api_key_enc"] ?? null;
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
