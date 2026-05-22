<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksWorkspace;
use Modules\Companies\Infrastructure\Models\Company;

class CompanyPolicy
{
    use ChecksWorkspace;

    public function view(User $user, Company $company): bool
    {
        return $this->sameWorkspace($user, $company);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->sameWorkspace($user, $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->sameWorkspace($user, $company);
    }
}
