<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesPermission;

class UserPolicy
{
    use AuthorizesPermission;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $this->hasPermission($user, 'users.view')
            && $this->sameWorkspace($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->hasPermission($user, 'users.update')
            && $this->sameWorkspace($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $this->hasPermission($user, 'users.delete')
            && $this->sameWorkspace($user, $model);
    }

    public function invite(User $user): bool
    {
        return $this->hasPermission($user, 'users.invite');
    }

    protected function sameWorkspace(User $actor, User $target): bool
    {
        $workspaceId = request()->attributes->get('workspace_id');

        if (! $workspaceId) {
            return $target->workspaces()
                ->whereIn('workspaces.id', $actor->workspaces()->pluck('workspaces.id'))
                ->exists();
        }

        return $target->workspaces()->where('workspaces.id', (int) $workspaceId)->exists();
    }
}
