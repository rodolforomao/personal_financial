<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Application\Services\OperationSummaryService;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Operations\Infrastructure\Models\OperationUnit;

class OperationController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('operations.index', [
            'operations' => Operation::query()
                ->where('workspace_id', $workspaceId)
                ->with(['company', 'units'])
                ->withCount('units')
                ->latest()
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('operations.create', [
            'companies' => Company::query()
                ->where('workspace_id', $workspaceId)
                ->orderBy('name')
                ->get(),
            'preselectedCompanyId' => $request->integer('company_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|integer|exists:companies,id',
            'description' => 'nullable|string|max:2000',
            'partners_count' => 'nullable|integer|min:2|max:99',
            'total_invested' => 'nullable|numeric|min:0',
        ]);

        $slug = $this->uniqueSlug($workspaceId, Str::slug($validated['name']));

        $operation = Operation::query()->create([
            'workspace_id' => $workspaceId,
            'company_id' => $validated['company_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'exclude_from_main_dashboard' => true,
            'partners_count' => $validated['partners_count'] ?? null,
            'total_invested' => $validated['total_invested'] ?? null,
        ]);

        return redirect()
            ->route('operations.show', $operation)
            ->with('success', 'Operação criada. Cadastre as unidades abaixo.');
    }

    public function show(
        Request $request,
        Operation $operation,
        OperationSummaryService $summary,
    ): View {
        $this->authorize('view', $operation);
        $operation->load(['company', 'units']);

        return view('operations.show', [
            'operation' => $operation,
            'summary' => $summary->forOperation($operation),
            'recentTransactions' => Transaction::query()
                ->where('operation_id', $operation->id)
                ->with(['category', 'operationUnit', 'linkedTransaction'])
                ->latest('transaction_date')
                ->limit(15)
                ->get(),
        ]);
    }

    public function edit(Request $request, Operation $operation): View
    {
        $this->authorize('update', $operation);
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('operations.edit', [
            'operation' => $operation,
            'companies' => Company::query()
                ->where('workspace_id', $workspaceId)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Operation $operation): RedirectResponse
    {
        $this->authorize('update', $operation);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|integer|exists:companies,id',
            'description' => 'nullable|string|max:2000',
            'exclude_from_main_dashboard' => 'nullable|boolean',
            'partners_count' => 'nullable|integer|min:2|max:99',
            'total_invested' => 'nullable|numeric|min:0',
        ]);

        $operation->update([
            'name' => $validated['name'],
            'company_id' => $validated['company_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'exclude_from_main_dashboard' => $request->boolean('exclude_from_main_dashboard'),
            'partners_count' => $validated['partners_count'] ?? null,
            'total_invested' => isset($validated['total_invested']) && $validated['total_invested'] !== '' ? $validated['total_invested'] : null,
        ]);

        return redirect()
            ->route('operations.show', $operation)
            ->with('success', 'Operação atualizada.');
    }

    public function storeUnit(Request $request, Operation $operation): RedirectResponse
    {
        $this->authorize('update', $operation);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:32',
        ]);

        $maxOrder = $operation->units()->max('sort_order') ?? 0;

        OperationUnit::query()->create([
            'operation_id' => $operation->id,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('operations.show', $operation)
            ->with('success', 'Unidade cadastrada.');
    }

    public function destroyUnit(Request $request, Operation $operation, OperationUnit $unit): RedirectResponse
    {
        $this->authorize('update', $operation);

        if ($unit->operation_id !== $operation->id) {
            abort(404);
        }

        if ($unit->transactions()->exists()) {
            return back()->with('error', 'Não é possível remover: há lançamentos vinculados a esta unidade.');
        }

        $unit->delete();

        return redirect()
            ->route('operations.show', $operation)
            ->with('success', 'Unidade removida.');
    }

    protected function uniqueSlug(int $workspaceId, string $base): string
    {
        $slug = $base;
        $n = 1;
        while (Operation::query()->where('workspace_id', $workspaceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }
}
