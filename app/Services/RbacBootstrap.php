<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacBootstrap
{
    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        return [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.invite',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'audit.view',
            'settings.manage',
            'sessions.view',
            'sessions.revoke',
            'tokens.manage',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function roleMatrix(): array
    {
        $all = self::permissions();

        return [
            'SUPER_ADMIN' => $all,
            'TENANT_OWNER' => $all,
            'ADMIN' => $all,
            'MANAGER' => ['users.view', 'users.create', 'users.update', 'users.invite', 'roles.view', 'permissions.view', 'sessions.view', 'tokens.manage'],
            'ANALYST' => ['users.view', 'permissions.view'],
            'AUDITOR' => ['users.view', 'audit.view', 'permissions.view'],
            'VIEWER' => ['users.view'],
            'admin' => $all,
            'finance_manager' => ['users.view'],
            'viewer' => ['users.view'],
        ];
    }

    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::roleMatrix() as $roleName => $grants) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($grants);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function isProvisioned(): bool
    {
        return Permission::query()->where('guard_name', 'web')->where('name', 'users.view')->exists();
    }
}
