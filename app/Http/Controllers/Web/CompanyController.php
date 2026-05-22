<?php

namespace App\Http\Controllers\Web;

use App\Core\Enums\CompanyType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Companies\Infrastructure\Models\Company;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = $request->attributes->get('workspace_id');

        return view('companies.index', [
            'companies' => Company::query()
                ->where('workspace_id', $workspaceId)
                ->with('contracts')
                ->orderBy('type')
                ->orderBy('name')
                ->paginate(15),
            'byType' => Company::query()
                ->where('workspace_id', $workspaceId)
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type'),
        ]);
    }

    public function create(): View
    {
        return view('companies.create', [
            'types' => CompanyType::forForm(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:own,partner,payer,employer,investment',
            'partnership_share' => 'nullable|numeric|min:0|max:100',
            'expected_monthly_revenue' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        Company::query()->create([
            'workspace_id' => $request->attributes->get('workspace_id'),
            'name' => $validated['name'],
            'type' => CompanyType::from($validated['type']),
            'partnership_share' => $validated['partnership_share'] ?? null,
            'expected_monthly_revenue' => $validated['expected_monthly_revenue'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('companies.index')->with('success', 'Empresa criada.');
    }
}
