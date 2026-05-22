<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Infrastructure\Models\FinancialAccount;

class FinancialAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            FinancialAccount::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:checking,savings,investment,cash',
            'bank_name' => 'nullable|string|max:255',
            'opening_balance' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
        ]);

        $account = FinancialAccount::query()->create([
            ...$validated,
            'workspace_id' => $request->attributes->get('workspace_id'),
            'current_balance' => $validated['opening_balance'] ?? 0,
        ]);

        return response()->json($account, 201);
    }
}
