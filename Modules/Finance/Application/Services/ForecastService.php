<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionType;
use Illuminate\Support\Carbon;
use Modules\Finance\Application\DTOs\DashboardFilter;
use Modules\Finance\Infrastructure\Models\FinancialForecast;
use Modules\Finance\Infrastructure\Models\RecurringItem;
use Modules\Finance\Infrastructure\Models\Transaction;

class ForecastService
{
    public function generate(int $workspaceId, int $horizonDays = 90, ?DashboardFilter $filter = null): FinancialForecast
    {
        $filter ??= DashboardFilter::consolidated();
        $start = now();
        $end = now()->addDays($horizonDays);

        $recurringIncome = RecurringItem::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', TransactionType::Income->value)
            ->where('is_active', true)
            ->sum('amount');

        $recurringExpense = RecurringItem::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', TransactionType::Expense->value)
            ->where('is_active', true)
            ->sum('amount');

        $pendingIncome = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->forDashboard($filter)
            ->where('type', TransactionType::Income)
            ->where('status', 'pending')
            ->whereBetween('due_date', [$start, $end])
            ->sum('amount');

        $pendingExpense = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->forDashboard($filter)
            ->where('type', TransactionType::Expense)
            ->where('status', 'pending')
            ->whereBetween('due_date', [$start, $end])
            ->sum('amount');

        $months = max(1, $horizonDays / 30);
        $projectedIncome = ($recurringIncome * $months) + $pendingIncome;
        $projectedExpense = ($recurringExpense * $months) + $pendingExpense;
        $projectedBalance = $projectedIncome - $projectedExpense;

        $risk = match (true) {
            $projectedBalance < 0 => 'critical',
            $projectedBalance < ($recurringExpense * 0.5) => 'high',
            $projectedBalance < $recurringExpense => 'medium',
            default => 'low',
        };

        return FinancialForecast::query()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'forecast_date' => now()->toDateString(),
                'horizon_days' => $horizonDays,
            ],
            [
                'projected_income' => $projectedIncome,
                'projected_expense' => $projectedExpense,
                'projected_balance' => $projectedBalance,
                'risk_level' => $risk,
                'details' => [
                    'recurring_income' => $recurringIncome,
                    'recurring_expense' => $recurringExpense,
                    'pending_income' => $pendingIncome,
                    'pending_expense' => $pendingExpense,
                    'horizon_days' => $horizonDays,
                ],
            ]
        );
    }
}
