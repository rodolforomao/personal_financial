<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Support\Carbon;
use Modules\Finance\Application\DTOs\DashboardFilter;
use Modules\Finance\Infrastructure\Models\CashFlowSnapshot;
use Modules\Finance\Infrastructure\Models\Transaction;

class CashFlowService
{
    public function snapshot(int $workspaceId, ?Carbon $date = null, ?DashboardFilter $filter = null): CashFlowSnapshot
    {
        $date ??= now();
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();
        $filter ??= DashboardFilter::consolidated();

        $transactions = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->forDashboard($filter)
            ->whereIn('status', [TransactionStatus::Confirmed, TransactionStatus::Reconciled])
            ->whereBetween('transaction_date', [$start, $end])
            ->get();

        $income = $transactions->where('type', TransactionType::Income)->sum('amount');
        $expense = $transactions->where('type', TransactionType::Expense)->sum('amount');

        return CashFlowSnapshot::query()->updateOrCreate(
            ['workspace_id' => $workspaceId, 'snapshot_date' => $date->toDateString()],
            [
                'total_income' => $income,
                'total_expense' => $expense,
                'net_cash_flow' => $income - $expense,
                'balance' => $income - $expense,
                'breakdown' => [
                    'income_count' => $transactions->where('type', TransactionType::Income)->count(),
                    'expense_count' => $transactions->where('type', TransactionType::Expense)->count(),
                ],
            ]
        );
    }

    public function dashboard(int $workspaceId, ?DashboardFilter $filter = null): array
    {
        $filter ??= DashboardFilter::consolidated();
        $current = $this->snapshot($workspaceId, null, $filter);
        $previous = $this->snapshot($workspaceId, now()->subMonth(), $filter);

        return [
            'current_month' => $current,
            'previous_month' => $previous,
            'income_change_pct' => $this->percentChange(
                (float) $previous->total_income,
                (float) $current->total_income
            ),
            'expense_change_pct' => $this->percentChange(
                (float) $previous->total_expense,
                (float) $current->total_expense
            ),
        ];
    }

    protected function percentChange(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
