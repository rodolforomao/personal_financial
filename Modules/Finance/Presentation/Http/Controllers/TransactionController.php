<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Core\Enums\RecurrenceFrequency;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Finance\Infrastructure\Repositories\TransactionRepository;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionRepository $repository): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return response()->json(
            $repository->forWorkspace($workspaceId, $request->query('from'), $request->query('to'))
        );
    }

    public function store(Request $request, CreateTransactionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense,transfer',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'transaction_date' => 'required|date',
            'financial_account_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'project_id' => 'nullable|integer',
            'operation_id' => 'nullable|integer',
            'operation_unit_id' => 'nullable|integer',
            'counterparty' => 'nullable|string|max:255',
            'funding_source' => 'nullable|string|max:32',
            'payment_method' => 'nullable|string|max:32',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:pending,confirmed,cancelled,reconciled',
            'is_recurring' => 'sometimes|boolean',
            'recurrence_frequency' => 'nullable|in:weekly,biweekly,monthly,quarterly,yearly',
        ]);

        $isRecurring = $request->boolean('is_recurring') && ! empty($validated['recurrence_frequency']);

        $transaction = $action->execute(new CreateTransactionData(
            workspaceId: (int) $request->attributes->get('workspace_id'),
            type: TransactionType::from($validated['type']),
            amount: (float) $validated['amount'],
            description: $validated['description'],
            transactionDate: $validated['transaction_date'],
            financialAccountId: $validated['financial_account_id'] ?? null,
            categoryId: $validated['category_id'] ?? null,
            companyId: $validated['company_id'] ?? null,
            projectId: $validated['project_id'] ?? null,
            operationId: $validated['operation_id'] ?? null,
            operationUnitId: $validated['operation_unit_id'] ?? null,
            status: isset($validated['status']) ? TransactionStatus::from($validated['status']) : TransactionStatus::Pending,
            counterparty: $validated['counterparty'] ?? null,
            fundingSource: $validated['funding_source'] ?? null,
            paymentMethod: $validated['payment_method'] ?? null,
            dueDate: $validated['due_date'] ?? null,
            isRecurring: $isRecurring,
            recurrenceFrequency: $isRecurring
                ? RecurrenceFrequency::from($validated['recurrence_frequency'])
                : null,
        ));

        return response()->json($transaction->load(['category', 'company', 'recurringItem']), 201);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        return response()->json($transaction->load(['category', 'company', 'project']));
    }

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        $transaction->update($request->validate([
            'status' => 'sometimes|in:pending,confirmed,cancelled,reconciled',
            'category_id' => 'sometimes|nullable|integer',
            'amount' => 'sometimes|numeric|min:0.01',
            'description' => 'sometimes|string|max:500',
            'funding_source' => 'sometimes|nullable|string|max:32',
            'payment_method' => 'sometimes|nullable|string|max:32',
        ]));

        return response()->json($transaction->fresh());
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $transaction);
        $transaction->delete();

        return response()->json(null, 204);
    }
}
