<?php

namespace Modules\Categorization;

use App\Core\Contracts\ModuleInterface;

class CategorizationModule implements ModuleInterface
{
    public function name(): string
    {
        return 'categorization';
    }

    public function register(): void {}

    public function boot(): void {}
}
