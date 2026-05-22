<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Infrastructure\Models\FeatureFlag as FeatureFlagModel;

class FeatureFlag
{
    public static function enabled(string $key, ?int $workspaceId = null, bool $default = false): bool
    {
        $cacheKey = "feature_flag:{$workspaceId}:{$key}";

        return Cache::remember($cacheKey, 300, function () use ($key, $workspaceId, $default) {
            $flag = FeatureFlagModel::query()
                ->where('key', $key)
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->first();

            return $flag?->enabled ?? $default;
        });
    }
}
