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
     *     my_income: float,
     *     my_net: float,
     *     income_count: int,
     *     expense_count: int,
     *     has_partners: bool,
     *     partners_count: int|null,
     *     total_invested: float|null,
     *     total_income_alltime: float,
     *     my_total_income_alltime: float,
     *     units: list<array{unit: OperationUnit, income: float, expense: float, net: float, my_income: float, my_net: float}>
     * }
     */
    public function forOperation(Operation $operation, ?Carbon $month = null): array
    {
        $month ??= now();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $partnersCount = $operation->partners_count && $operation->partners_count > 1
            ? $operation->partners_count
            : null;

        $transactions = Transaction::query()
            ->where('operation_id', $operation->id)
            ->whereIn('status', [TransactionStatus::Confirmed, TransactionStatus::Reconciled])
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $income = (float) $transactions->where('type', TransactionType::Income)->sum('amount');
        $expense = (float) $transactions->where('type', TransactionType::Expense)->sum('amount');

        $myIncome = $partnersCount ? $income / $partnersCount : $income;
        $myNet = $partnersCount ? ($income - $expense) / $partnersCount : ($income - $expense);

        $units = $operation->units()->get()->map(function (OperationUnit $unit) use ($transactions, $partnersCount) {
            $unitRows = $transactions->where('operation_unit_id', $unit->id);
            $inc = (float) $unitRows->where('type', TransactionType::Income)->sum('amount');
            $exp = (float) $unitRows->where('type', TransactionType::Expense)->sum('amount');

            return [
                'unit' => $unit,
                'income' => $inc,
                'expense' => $exp,
                'net' => $inc - $exp,
                'my_income' => $partnersCount ? $inc / $partnersCount : $inc,
                'my_net' => $partnersCount ? ($inc - $exp) / $partnersCount : ($inc - $exp),
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
                'my_income' => $partnersCount ? $inc / $partnersCount : $inc,
                'my_net' => $partnersCount ? ($inc - $exp) / $partnersCount : ($inc - $exp),
            ];
        }

        $totalIncomeAlltime = (float) Transaction::query()
            ->where('operation_id', $operation->id)
            ->whereIn('status', [TransactionStatus::Confirmed, TransactionStatus::Reconciled])
            ->where('type', TransactionType::Income)
            ->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'my_income' => $myIncome,
            'my_net' => $myNet,
            'income_count' => $transactions->where('type', TransactionType::Income)->count(),
            'expense_count' => $transactions->where('type', TransactionType::Expense)->count(),
            'has_partners' => $partnersCount !== null,
            'partners_count' => $partnersCount,
            'total_invested' => $operation->total_invested ? (float) $operation->total_invested : null,
            'total_income_alltime' => $totalIncomeAlltime,
            'my_total_income_alltime' => $partnersCount ? $totalIncomeAlltime / $partnersCount : $totalIncomeAlltime,
            'units' => $units,
        ];
    }
}
