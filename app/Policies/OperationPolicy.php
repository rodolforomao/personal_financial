<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksWorkspace;
use Modules\Operations\Infrastructure\Models\Operation;

class OperationPolicy
{
    use ChecksWorkspace;

    public function view(User $user, Operation $operation): bool
    {
        return $this->sameWorkspace($user, $operation);
    }

    public function update(User $user, Operation $operation): bool
    {
        return $this->sameWorkspace($user, $operation);
    }

    public function delete(User $user, Operation $operation): bool
    {
        return $this->sameWorkspace($user, $operation);
    }
}
