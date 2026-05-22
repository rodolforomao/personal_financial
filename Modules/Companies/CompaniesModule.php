<?php

namespace Modules\Companies;

use App\Core\Contracts\ModuleInterface;

class CompaniesModule implements ModuleInterface
{
    public function name(): string
    {
        return 'companies';
    }

    public function register(): void {}

    public function boot(): void {}
}
