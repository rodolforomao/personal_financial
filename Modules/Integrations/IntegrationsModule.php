<?php

namespace Modules\Integrations;

use App\Core\Contracts\ModuleInterface;
use Illuminate\Support\Facades\Route;

class IntegrationsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'integrations';
    }

    public function register(): void {}

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v1/webhooks')
            ->group(__DIR__.'/Presentation/Routes/webhooks.php');
    }
}
