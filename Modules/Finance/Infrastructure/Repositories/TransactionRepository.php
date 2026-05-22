<?php

namespace Modules\Finance\Infrastructure\Repositories;

use App\Core\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Infrastructure\Models\Transaction;

class TransactionRepository implements RepositoryInterface
{
    public function find(int|string $id): ?Transaction
    {
        return Transaction::query()->find($id);
    }

    public function create(array $attributes): Transaction
    {
        return Transaction::query()->create($attributes);
    }

    public function update(Model $model, array $attributes): Transaction
    {
        $model->update($attributes);

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function forWorkspace(int $workspaceId, ?string $from = null, ?string $to = null): Collection
    {
        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->orderByDesc('transaction_date')
            ->get();
    }
}
