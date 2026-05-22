<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Application\Services\CashFlowService;
use Modules\Finance\Application\Services\ForecastService;
use Modules\Finance\Infrastructure\Models\Asset;
use Modules\Projects\Infrastructure\Models\Project;

class DashboardController extends Controller
{
    public function index(Request $request, CashFlowService $cashFlow, ForecastService $forecast): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return response()->json([
            'cash_flow' => $cashFlow->dashboard($workspaceId),
            'forecast' => $forecast->generate($workspaceId),
            'patrimony' => Asset::query()->where('workspace_id', $workspaceId)->sum('current_value'),
            'projects' => Project::query()
                ->where('workspace_id', $workspaceId)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'roi' => round($p->roi(), 2),
                    'profit' => $p->total_revenue - $p->total_cost,
                ]),
        ]);
    }
}
