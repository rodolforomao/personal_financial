<?php

namespace Modules\Finance\Infrastructure\Observers;

use Modules\Finance\Infrastructure\Models\FinancialAccount;
use Modules\Finance\Infrastructure\Models\Transaction;

class TransactionObserver
{
    public function saved(Transaction $transaction): void
    {
        if (! $transaction->financial_account_id || $transaction->status->value !== 'confirmed') {
            return;
        }

        $account = FinancialAccount::query()->find($transaction->financial_account_id);
        if (! $account) {
            return;
        }

        $balance = Transaction::query()
            ->where('financial_account_id', $account->id)
            ->where('status', 'confirmed')
            ->selectRaw("SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as balance")
            ->value('balance');

        $account->update(['current_balance' => ($account->opening_balance ?? 0) + ($balance ?? 0)]);
    }
}
