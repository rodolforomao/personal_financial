<?php

namespace Modules\Intelligence\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Intelligence\Application\Jobs\RunFinancialAnalysisJob;
use Modules\Intelligence\Infrastructure\Models\AiInsight;

class AiInsightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            AiInsight::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->orderByDesc('detected_at')
                ->paginate(30)
        );
    }

    public function triggerAnalysis(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        RunFinancialAnalysisJob::dispatch($workspaceId)->onQueue('ai');

        return response()->json(['message' => 'Análise financeira enfileirada.']);
    }
}
