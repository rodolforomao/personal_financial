<?php

namespace Modules\Companies\Presentation\Http\Controllers;

use App\Core\Enums\CompanyType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Companies\Infrastructure\Models\Company;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->where('workspace_id', $request->attributes->get('workspace_id'))
            ->with('contracts')
            ->latest()
            ->paginate(20);

        return response()->json($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:own,partner,payer,client,employer,investment',
            'partnership_share' => 'nullable|numeric|min:0|max:100',
            'document' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'expected_monthly_revenue' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $company = Company::query()->create([
            ...$validated,
            'workspace_id' => $request->attributes->get('workspace_id'),
            'type' => CompanyType::from($validated['type'] === 'client' ? 'payer' : $validated['type']),
            'partnership_share' => $validated['partnership_share'] ?? null,
        ]);

        return response()->json($company, 201);
    }

    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        return response()->json($company->load('contracts'));
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $company->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string',
            'expected_monthly_revenue' => 'sometimes|nullable|numeric',
            'notes' => 'sometimes|nullable|string',
        ]));

        return response()->json($company);
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $company->delete();

        return response()->json(null, 204);
    }
}
