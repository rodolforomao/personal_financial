<?php

namespace Modules\Operations;

use App\Core\Contracts\ModuleInterface;

class OperationsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'operations';
    }

    public function register(): void {}

    public function boot(): void {}
}
