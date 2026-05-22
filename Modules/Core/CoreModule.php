<?php

namespace Modules\Core;

use App\Core\Contracts\ModuleInterface;
use Illuminate\Support\Facades\Route;

class CoreModule implements ModuleInterface
{
    public function name(): string
    {
        return 'core';
    }

    public function register(): void {}

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->name('api.')
            ->group(__DIR__.'/Presentation/Routes/api.php');
    }
}
