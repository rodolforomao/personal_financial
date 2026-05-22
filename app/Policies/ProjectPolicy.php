<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksWorkspace;
use Modules\Projects\Infrastructure\Models\Project;

class ProjectPolicy
{
    use ChecksWorkspace;

    public function view(User $user, Project $project): bool
    {
        return $this->sameWorkspace($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->sameWorkspace($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->sameWorkspace($user, $project);
    }
}
