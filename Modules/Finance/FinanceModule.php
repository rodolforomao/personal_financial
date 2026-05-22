<?php

namespace Modules\Finance;

use App\Core\Contracts\ModuleInterface;
use Illuminate\Support\Facades\Event;
use Modules\Finance\Domain\Events\TransactionCreated;
use Modules\Finance\Infrastructure\Observers\TransactionObserver;

class FinanceModule implements ModuleInterface
{
    public function name(): string
    {
        return 'finance';
    }

    public function register(): void
    {
        app()->singleton(
            \Modules\Finance\Infrastructure\Repositories\TransactionRepository::class
        );
    }

    public function boot(): void
    {
        \Modules\Finance\Infrastructure\Models\Transaction::observe(TransactionObserver::class);
        Event::listen(TransactionCreated::class, function (TransactionCreated $event) {
            app(\Modules\Finance\Application\Services\CashFlowService::class)
                ->snapshot($event->transaction->workspace_id);
        });
    }

}
