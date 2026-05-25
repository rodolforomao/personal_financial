<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Finance\Infrastructure\Models\Asset;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('finance.assets.index', [
            'assets' => Asset::query()
                ->where('workspace_id', $workspaceId)
                ->orderByDesc('current_value')
                ->get(),
            'total' => Asset::query()->where('workspace_id', $workspaceId)->sum('current_value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:property,vehicle,investment,cash,other',
            'current_value' => 'required|numeric|min:0',
            'acquisition_value' => 'nullable|numeric|min:0',
            'acquired_at' => 'nullable|date',
        ]);

        Asset::query()->create([
            'workspace_id' => $request->attributes->get('workspace_id'),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'current_value' => $validated['current_value'],
            'acquisition_value' => $validated['acquisition_value'] ?? null,
            'acquired_at' => $validated['acquired_at'] ?? null,
        ]);

        return back()->with('success', 'Patrimônio cadastrado.');
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($asset->workspace_id === (int) $request->attributes->get('workspace_id'), 404);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:property,vehicle,investment,cash,other',
            'current_value' => 'required|numeric|min:0',
            'acquisition_value' => 'nullable|numeric|min:0',
            'acquired_at' => 'nullable|date',
        ]);

        $asset->update($validated);

        return back()->with('success', 'Patrimônio atualizado.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($asset->workspace_id === (int) $request->attributes->get('workspace_id'), 404);
        $asset->delete();

        return back()->with('success', 'Patrimônio removido.');
    }
}
