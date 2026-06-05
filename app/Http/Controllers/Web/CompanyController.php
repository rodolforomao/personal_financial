<?php

namespace App\Http\Controllers\Web;

use App\Core\Enums\CompanyType;
use App\Core\Support\DecimalComparer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Infrastructure\Models\Transaction;

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
                ->get(),
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
        $validated = $this->validatedFields($request);

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

    public function edit(Company $company): View
    {
        $this->authorize('update', $company);

        return view('companies.edit', [
            'company' => $company,
            'types' => CompanyType::forForm(),
            'transactionCount' => Transaction::query()->where('company_id', $company->id)->count(),
            'requirePassword' => config('financial.security.require_password_for_transaction_sensitive_edit', true),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $validated = $this->validatedFields($request);

        if ($this->sensitiveFieldsChanged($request, $company)
            && config('financial.security.require_password_for_transaction_sensitive_edit', true)) {
            $request->validate(['current_password' => ['required', 'current_password']]);
        }

        $company->update([
            'name' => $validated['name'],
            'type' => CompanyType::from($validated['type']),
            'partnership_share' => $validated['partnership_share'] ?? null,
            'expected_monthly_revenue' => $validated['expected_monthly_revenue'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', 'Empresa atualizada.');
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $request->validate([
            'confirm_delete' => 'accepted',
            'delete_confirmation' => 'required|in:EXCLUIR',
            'current_password' => ['required', 'current_password'],
        ], [
            'confirm_delete.accepted' => 'Marque que entende que a empresa será ocultada.',
            'delete_confirmation.in' => 'Digite exatamente EXCLUIR para confirmar.',
        ]);

        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', 'Empresa "'.$company->name.'" excluída (exclusão lógica).');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedFields(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:own,partner,payer,employer,investment,client',
            'partnership_share' => 'nullable|numeric|min:0|max:100',
            'expected_monthly_revenue' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);
    }

    protected function sensitiveFieldsChanged(Request $request, Company $company): bool
    {
        if ($request->input('name') !== $company->name) {
            return true;
        }

        if ($request->input('type') !== $company->type->value) {
            return true;
        }

        $share = $request->input('partnership_share');
        $oldShare = $company->partnership_share !== null ? (string) $company->partnership_share : '';
        $newShare = $share !== null && $share !== '' ? (string) $share : '';
        if ($newShare !== $oldShare) {
            return true;
        }

        if (DecimalComparer::differs(
            $request->input('expected_monthly_revenue') ?? 0,
            $company->expected_monthly_revenue ?? 0
        )) {
            return true;
        }

        return false;
    }
}
