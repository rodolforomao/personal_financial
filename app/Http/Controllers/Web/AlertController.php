<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Alerts\Infrastructure\Models\Alert;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $severity    = $request->input('severity', '');
        $status      = $request->input('status', '');

        return view('alerts.index', [
            'alerts' => Alert::query()
                ->where('workspace_id', $workspaceId)
                ->when($severity, fn ($q) => $q->where('severity', $severity))
                ->when($status === 'unread', fn ($q) => $q->where('is_read', false))
                ->when($status === 'read',   fn ($q) => $q->where('is_read', true))
                ->orderByDesc('triggered_at')
                ->paginate(20)
                ->withQueryString(),
            'severityFilter' => $severity,
            'statusFilter'   => $status,
        ]);
    }

    public function markRead(Request $request, Alert $alert): RedirectResponse|JsonResponse
    {
        $alert->update(['is_read' => true]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Alerta marcado como lido.');
    }

    public function bulkMarkRead(Request $request): RedirectResponse
    {
        $ids = array_filter((array) $request->input('alert_ids', []), 'is_numeric');
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $count = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_read', false)
            ->whereIn('id', $ids)
            ->update(['is_read' => true]);

        return back()->with('success', $count === 1 ? '1 alerta marcado como lido.' : "{$count} alertas marcados como lidos.");
    }
}
