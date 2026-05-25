<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Alerts\Infrastructure\Models\Alert;
use Modules\Finance\Application\Services\CashFlowService;
use Modules\Finance\Application\Services\DataHygieneService;
use Modules\Finance\Application\Services\CltSalaryService;
use Modules\Finance\Application\Services\DashboardChartService;
use Modules\Finance\Application\Services\DashboardFilterService;
use Modules\Finance\Application\Services\ForecastService;
use Modules\Finance\Infrastructure\Models\Asset;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Intelligence\Infrastructure\Models\AiInsight;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Projects\Infrastructure\Models\Project;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        CashFlowService $cashFlow,
        ForecastService $forecast,
        DashboardChartService $charts,
        CltSalaryService $cltSalaries,
        DashboardFilterService $dashboardFilter,
        DataHygieneService $dataHygiene,
    ): View {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $cltSalaries->ensurePendingPeriods($workspaceId);
        $filter = $dashboardFilter->fromSession($request);
        $hygieneAudit = $dataHygiene->audit($workspaceId);

        return view('dashboard.index', [
            'hygieneAudit' => $hygieneAudit,
            'dashboardFilter' => $filter,
            'operations' => Operation::query()
                ->where('workspace_id', $workspaceId)
                ->orderBy('name')
                ->get(['id', 'name', 'exclude_from_main_dashboard']),
            'pendingCltPeriods' => $cltSalaries->pendingPeriods($workspaceId),
            'cashFlow' => $cashFlow->dashboard($workspaceId, $filter),
            'forecast' => $forecast->generate($workspaceId, filter: $filter),
            'patrimony' => Asset::query()->where('workspace_id', $workspaceId)->sum('current_value'),
            'chartCashFlow' => $charts->monthlyCashFlow($workspaceId, filter: $filter),
            'chartExpenses' => $charts->expensesByCategory($workspaceId, filter: $filter),
            'chartPatrimony' => $charts->patrimonyBreakdown($workspaceId),
            'recentTransactions' => Transaction::query()
                ->where('workspace_id', $workspaceId)
                ->forDashboard($filter)
                ->with(['category', 'operation'])
                ->latest('transaction_date')
                ->limit(8)
                ->get(),
            'projects' => Project::query()->where('workspace_id', $workspaceId)->limit(5)->get(),
            'openAlerts' => Alert::query()
                ->where('workspace_id', $workspaceId)
                ->where('is_read', false)
                ->orderByDesc('triggered_at')
                ->limit(5)
                ->get(),
            'insights' => AiInsight::query()
                ->where('workspace_id', $workspaceId)
                ->where('is_resolved', false)
                ->orderByDesc('detected_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function updateFilter(Request $request, DashboardFilterService $dashboardFilter): RedirectResponse
    {
        $dashboardFilter->persist($request);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Filtro do dashboard atualizado.');
    }
}
