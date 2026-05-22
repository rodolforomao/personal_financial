<?php

namespace Modules\Alerts\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Alerts\Application\Services\AlertNotificationService;
use Modules\Alerts\Infrastructure\Models\Alert;

class DispatchAlertJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $alertId) {}

    public function handle(AlertNotificationService $service): void
    {
        $alert = Alert::query()->findOrFail($this->alertId);
        $service->dispatch($alert);
    }
}
