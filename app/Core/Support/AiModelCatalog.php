<?php

namespace App\Core\Support;

class AiModelCatalog
{
    public static function forProvider(string $provider): array
    {
        return config("financial.ai.models.{$provider}", []);
    }

    public static function defaultFor(string $provider): string
    {
        return config("financial.ai.{$provider}.model")
            ?? array_key_first(static::forProvider($provider))
            ?? 'gpt-4o-mini';
    }

    public static function isValid(string $provider, string $model): bool
    {
        return array_key_exists($model, static::forProvider($provider));
    }

    public static function label(string $provider, string $model): string
    {
        $meta = static::forProvider($provider)[$model] ?? null;

        return is_array($meta) ? ($meta['label'] ?? $model) : $model;
    }

    public static function resolveModel(string $provider, ?string $userModel): string
    {
        if ($userModel && static::isValid($provider, $userModel)) {
            return $userModel;
        }

        return static::defaultFor($provider);
    }

    /** @return array<string, array<string, array>> */
    public static function allGrouped(): array
    {
        return [
            'openai' => static::forProvider('openai'),
            'openrouter' => static::forProvider('openrouter'),
        ];
    }
}
