<?php

namespace Modules\Operations\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Support\Carbon;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Operations\Infrastructure\Models\OperationUnit;

class OperationSummaryService
{
    /**
     * @return array{
     *     income: float,
     *     expense: float,
     *     net: float,
     *     income_count: int,
     *     expense_count: int,
     *     units: list<array{unit: OperationUnit, income: float, expense: float, net: float}>
     * }
     */
    public function forOperation(Operation $operation, ?Carbon $month = null): array
    {
        $month ??= now();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $transactions = Transaction::query()
            ->where('operation_id', $operation->id)
            ->whereIn('status', [TransactionStatus::Confirmed, TransactionStatus::Reconciled])
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $income = (float) $transactions->where('type', TransactionType::Income)->sum('amount');
        $expense = (float) $transactions->where('type', TransactionType::Expense)->sum('amount');

        $units = $operation->units()->get()->map(function (OperationUnit $unit) use ($transactions) {
            $unitRows = $transactions->where('operation_unit_id', $unit->id);
            $inc = (float) $unitRows->where('type', TransactionType::Income)->sum('amount');
            $exp = (float) $unitRows->where('type', TransactionType::Expense)->sum('amount');

            return [
                'unit' => $unit,
                'income' => $inc,
                'expense' => $exp,
                'net' => $inc - $exp,
            ];
        })->all();

        $unassigned = $transactions->whereNull('operation_unit_id');
        if ($unassigned->isNotEmpty()) {
            $inc = (float) $unassigned->where('type', TransactionType::Income)->sum('amount');
            $exp = (float) $unassigned->where('type', TransactionType::Expense)->sum('amount');
            $units[] = [
                'unit' => null,
                'income' => $inc,
                'expense' => $exp,
                'net' => $inc - $exp,
            ];
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'income_count' => $transactions->where('type', TransactionType::Income)->count(),
            'expense_count' => $transactions->where('type', TransactionType::Expense)->count(),
            'units' => $units,
        ];
    }
}
