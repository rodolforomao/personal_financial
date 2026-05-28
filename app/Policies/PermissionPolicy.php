<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesPermission;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    use AuthorizesPermission;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $this->hasPermission($user, 'permissions.view');
    }
}
