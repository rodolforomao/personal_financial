<?php

namespace Modules\Finance\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Finance\Infrastructure\Models\Transaction;

class TransactionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Transaction $transaction) {}
}
