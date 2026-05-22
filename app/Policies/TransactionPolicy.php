<?php

namespace App\Policies;

use App\Models\User;
use Modules\Finance\Infrastructure\Models\Transaction;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return $user->workspaces()->where('workspaces.id', $transaction->workspace_id)->exists();
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }
}
