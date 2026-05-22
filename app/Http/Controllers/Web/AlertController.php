<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Alerts\Infrastructure\Models\Alert;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        return view('alerts.index', [
            'alerts' => Alert::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->orderByDesc('triggered_at')
                ->paginate(20),
        ]);
    }

    public function markRead(Alert $alert): RedirectResponse
    {
        $alert->update(['is_read' => true]);

        return back()->with('success', 'Alerta marcado como lido.');
    }
}
