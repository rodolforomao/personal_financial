<?php

namespace Modules\Intelligence\Application\Services;

use Modules\Alerts\Infrastructure\Models\Alert;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Services\CashFlowService;
use Modules\Finance\Application\Services\ForecastService;
use Modules\Finance\Infrastructure\Models\RecurringItem;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Projects\Infrastructure\Models\Project;

class FinancialContextBuilder
{
    public function build(int $workspaceId): string
    {
        $cashFlow = app(CashFlowService::class)->dashboard($workspaceId);
        $forecast = app(ForecastService::class)->generate($workspaceId);

        $context = [
            'cash_flow' => $cashFlow,
            'forecast' => $forecast->only(['projected_income', 'projected_expense', 'projected_balance', 'risk_level']),
            'recent_transactions' => Transaction::query()
                ->where('workspace_id', $workspaceId)
                ->latest('transaction_date')
                ->limit(50)
                ->get(['type', 'amount', 'description', 'counterparty', 'transaction_date', 'status']),
            'companies' => Company::query()->where('workspace_id', $workspaceId)->get(['name', 'type', 'status', 'expected_monthly_revenue']),
            'projects' => Project::query()->where('workspace_id', $workspaceId)->get(['name', 'status', 'total_cost', 'total_revenue']),
            'recurring' => RecurringItem::query()->where('workspace_id', $workspaceId)->where('is_active', true)->get(),
            'open_alerts' => Alert::query()->where('workspace_id', $workspaceId)->where('is_read', false)->limit(20)->get(),
        ];

        return json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
