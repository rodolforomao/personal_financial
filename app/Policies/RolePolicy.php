<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesPermission;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use AuthorizesPermission;

    private const PROTECTED_ROLES = ['SUPER_ADMIN', 'TENANT_OWNER'];

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->hasPermission($user, 'roles.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)
            && ! $user->hasRole('SUPER_ADMIN')) {
            return false;
        }

        return $this->hasPermission($user, 'roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return false;
        }

        return $this->hasPermission($user, 'roles.delete');
    }
}
