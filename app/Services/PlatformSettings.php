<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PlatformSettings
{
    /** @var array<string, string|null> */
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->available()) {
            return $default;
        }

        if (! array_key_exists($key, $this->cache)) {
            try {
                $setting = PlatformSetting::query()->find($key);
                $this->cache[$key] = $setting?->value;
            } catch (Throwable) {
                return $default;
            }
        }

        return array_key_exists($key, $this->cache) && $this->cache[$key] !== null
            ? $this->cache[$key]
            : $default;
    }

    public function has(string $key): bool
    {
        if (! $this->available()) {
            return false;
        }

        try {
            return PlatformSetting::query()->whereKey($key)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function put(string $key, mixed $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value],
        );

        $this->cache[$key] = $value === null ? null : (string) $value;
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
