<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class SafePermission
{
    public static function check(?User $user, string $permission, string $guard = 'web'): bool
    {
        if (! $user) {
            return false;
        }

        if (! Permission::query()->where('name', $permission)->where('guard_name', $guard)->exists()) {
            return false;
        }

        return $user->hasPermissionTo($permission, $guard);
    }
}
