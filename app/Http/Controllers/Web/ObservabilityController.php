<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\ObservabilityDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObservabilityController extends Controller
{
    public function index(Request $request, ObservabilityDashboardService $dashboard): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $canViewLogs = $request->user()->hasRole('admin');

        $filters = [
            'level' => $request->query('level'),
            'search' => $request->query('search'),
            'file' => $request->query('file'),
            'days' => $request->query('days', 7),
        ];

        $data = $dashboard->build($workspaceId, $canViewLogs, $filters);

        return view('observability.index', [
            'timeline' => $data['timeline'],
            'insights' => $data['insights'],
            'summary' => $data['summary'],
            'logFiles' => $data['logFiles'],
            'filters' => $filters,
            'canViewLogs' => $canViewLogs,
        ]);
    }
}
