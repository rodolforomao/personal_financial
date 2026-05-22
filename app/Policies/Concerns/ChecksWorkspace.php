<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksWorkspace
{
    protected function sameWorkspace(User $user, Model $model): bool
    {
        if (! isset($model->workspace_id)) {
            return false;
        }

        return $user->workspaces()->where('workspaces.id', $model->workspace_id)->exists();
    }
}
