<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\SafePermission;

trait AuthorizesPermission
{
    protected function hasPermission(User $user, string $permission): bool
    {
        return SafePermission::check($user, $permission);
    }
}
