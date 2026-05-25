<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Support\Carbon;
use Modules\Finance\Application\DTOs\DashboardFilter;
use Modules\Finance\Infrastructure\Models\Asset;
use Modules\Finance\Infrastructure\Models\Transaction;

class DashboardChartService
{
    /**
     * @return array{labels: list<string>, income: list<float>, expense: list<float>, net: list<float>}
     */
    public function monthlyCashFlow(int $workspaceId, int $months = 6, ?DashboardFilter $filter = null): array
    {
        $filter ??= DashboardFilter::consolidated();
        $labels = [];
        $income = [];
        $expense = [];
        $net = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $labels[] = $start->translatedFormat('M/y');

            $rows = Transaction::query()
                ->where('workspace_id', $workspaceId)
                ->forDashboard($filter)
                ->whereIn('status', [TransactionStatus::Confirmed, TransactionStatus::Reconciled])
                ->whereBetween('transaction_date', [$start, $end])
                ->get();

            $inc = (float) $rows->where('type', TransactionType::Income)->sum('amount');
            $exp = (float) $rows->where('type', TransactionType::Expense)->sum('amount');
            $income[] = round($inc, 2);
            $expense[] = round($exp, 2);
            $net[] = round($inc - $exp, 2);
        }

        return compact('labels', 'income', 'expense', 'net');
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     values: list<float>,
     *     category_ids: list<int|null>,
     *     counts: list<int>,
     *     total: float,
     *     month: array{from: string, to: string}
     * }
     */
    public function expensesByCategory(int $workspaceId, ?Carbon $month = null, ?DashboardFilter $filter = null): array
    {
        $filter ??= DashboardFilter::consolidated();
        $month ??= now();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $rows = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->forDashboard($filter)
            ->where('type', TransactionType::Expense)
            ->whereIn('status', [TransactionStatus::Confirmed, TransactionStatus::Reconciled])
            ->whereBetween('transaction_date', [$start, $end])
            ->with('category')
            ->get()
            ->groupBy(fn ($tx) => $tx->category_id ?: 'uncategorized')
            ->map(fn ($group) => [
                'category_id' => $group->first()->category_id ? (int) $group->first()->category_id : null,
                'label' => $group->first()->category?->name ?? 'Sem categoria',
                'value' => round((float) $group->sum('amount'), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values()
            ->take(8);

        $total = round((float) $rows->sum('value'), 2);

        return [
            'labels' => $rows->pluck('label')->all(),
            'values' => $rows->pluck('value')->all(),
            'category_ids' => $rows->pluck('category_id')->all(),
            'counts' => $rows->pluck('count')->all(),
            'total' => $total,
            'month' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, total: float}
     */
    public function patrimonyBreakdown(int $workspaceId): array
    {
        $assets = Asset::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('current_value')
            ->get();

        return [
            'labels' => $assets->pluck('name')->all(),
            'values' => $assets->map(fn ($a) => (float) $a->current_value)->all(),
            'total' => round((float) $assets->sum('current_value'), 2),
        ];
    }
}
