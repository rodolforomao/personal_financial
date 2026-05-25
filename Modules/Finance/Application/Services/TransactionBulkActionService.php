<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Operations\Infrastructure\Models\OperationUnit;

class TransactionBulkActionService
{
    public const SKIP_VALUE = '__skip__';

    public const CLEAR_VALUE = '__clear__';

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $changes  only keys to overwrite (null clears nullable fields)
     * @return array{updated: int, skipped: int}
     */
    public function apply(int $workspaceId, array $ids, array $changes, ?User $user = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $updated = 0;
        $skipped = 0;

        if ($ids === [] || $changes === []) {
            return ['updated' => 0, 'skipped' => 0];
        }

        if (isset($changes['operation_id']) || isset($changes['operation_unit_id'])) {
            $changes = array_merge($changes, $this->resolveOperationChanges($workspaceId, $changes));
        }

        $transactions = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids)
            ->get();

        foreach ($transactions as $transaction) {
            if ($user && Gate::forUser($user)->denies('update', $transaction)) {
                $skipped++;

                continue;
            }

            $transaction->update($changes);
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param  list<int>  $ids
     * @return array{deleted: int, skipped: int}
     */
    public function delete(int $workspaceId, array $ids, ?User $user = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $deleted = 0;
        $skipped = 0;

        $transactions = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids)
            ->get();

        foreach ($transactions as $transaction) {
            if ($user && Gate::forUser($user)->denies('delete', $transaction)) {
                $skipped++;

                continue;
            }

            $transaction->delete();
            $deleted++;
        }

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array{operation_id: ?int, operation_unit_id: ?int, company_id?: ?int}
     */
    protected function resolveOperationChanges(int $workspaceId, array $changes): array
    {
        $unitId = isset($changes['operation_unit_id']) && $changes['operation_unit_id'] !== ''
            ? (int) $changes['operation_unit_id']
            : null;
        $operationId = isset($changes['operation_id']) && $changes['operation_id'] !== ''
            ? (int) $changes['operation_id']
            : null;

        if ($unitId) {
            $unit = OperationUnit::query()
                ->whereKey($unitId)
                ->whereHas('operation', fn ($q) => $q->where('workspace_id', $workspaceId))
                ->with('operation')
                ->firstOrFail();

            return [
                'operation_id' => $unit->operation_id,
                'operation_unit_id' => $unit->id,
                'company_id' => $unit->operation->company_id,
            ];
        }

        if ($operationId) {
            $operation = Operation::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($operationId)
                ->firstOrFail();

            return [
                'operation_id' => $operation->id,
                'operation_unit_id' => null,
                'company_id' => $operation->company_id,
            ];
        }

        return [
            'operation_id' => null,
            'operation_unit_id' => null,
        ];
    }
}
