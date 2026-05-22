<?php

namespace Modules\Alerts;

use App\Core\Contracts\ModuleInterface;

class AlertsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'alerts';
    }

    public function register(): void {}

    public function boot(): void {}
}
