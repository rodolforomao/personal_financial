<?php

namespace Modules\Alerts\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Alerts\Infrastructure\Models\Alert;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Alert::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->when($request->boolean('unread'), fn ($q) => $q->where('is_read', false))
                ->orderByDesc('triggered_at')
                ->paginate(30)
        );
    }

    public function markRead(Alert $alert): JsonResponse
    {
        $alert->update(['is_read' => true]);

        return response()->json($alert);
    }
}
