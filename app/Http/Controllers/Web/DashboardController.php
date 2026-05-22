<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Alerts\Infrastructure\Models\Alert;
use Modules\Finance\Application\Services\CashFlowService;
use Modules\Finance\Application\Services\ForecastService;
use Modules\Finance\Infrastructure\Models\Asset;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Intelligence\Infrastructure\Models\AiInsight;
use Modules\Projects\Infrastructure\Models\Project;

class DashboardController extends Controller
{
    public function index(Request $request, CashFlowService $cashFlow, ForecastService $forecast): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('dashboard.index', [
            'cashFlow' => $cashFlow->dashboard($workspaceId),
            'forecast' => $forecast->generate($workspaceId),
            'patrimony' => Asset::query()->where('workspace_id', $workspaceId)->sum('current_value'),
            'recentTransactions' => Transaction::query()
                ->where('workspace_id', $workspaceId)
                ->with('category')
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
}
