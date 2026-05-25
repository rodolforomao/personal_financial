<?php

namespace App\Http\Controllers\Web;

use App\Core\Support\DecimalComparer;
use App\Core\Enums\RecurrenceFrequency;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ResolvesOperationOnTransaction;
use App\Http\Controllers\Web\Concerns\ValidatesTransactionPaymentFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Application\Services\ReceiptCategorySuggestionService;
use Modules\Categorization\Application\Services\BulkCategorizeTransactionsService;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Application\Services\TransactionBulkActionService;
use Modules\Finance\Application\Services\TransactionEstornoPairCleanupService;
use Modules\Finance\Application\Services\TransactionIndexFilterService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\OCR\Infrastructure\Models\Document;
use Modules\Operations\Infrastructure\Models\Operation;

class TransactionController extends Controller
{
    use ResolvesOperationOnTransaction;
    use ValidatesTransactionPaymentFields;
    public function index(Request $request, TransactionIndexFilterService $filters): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->with(['category', 'company', 'operation', 'operationUnit', 'recurringItem'])
            ->withCount('documents');

        $filters->apply($query, $request);

        $perPage = min(100, max(10, $request->integer('per_page', 25)));

        $bulkCategorize = app(BulkCategorizeTransactionsService::class);
        $estornoPairPreview = app(TransactionEstornoPairCleanupService::class)->preview($workspaceId);
        $baseQuery = fn () => Transaction::query()->where('workspace_id', $workspaceId);

        $operations = $this->operationsForForm($workspaceId);
        $selectedOperationId = $request->integer('operation_id') ?: null;
        $operationUnits = $selectedOperationId
            ? $operations->firstWhere('id', $selectedOperationId)?->activeUnits ?? collect()
            : collect();

        return view('finance.transactions.index', [
            'transactions' => $query->orderByDesc('transaction_date')->paginate($perPage)->withQueryString(),
            'filtersActive' => $filters->hasActiveFilters($request),
            'knownSources' => $filters->knownSources($workspaceId),
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'companies' => Company::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'operations' => $operations,
            'operationUnits' => $operationUnits,
            'uncategorizedCount' => $bulkCategorize->countUncategorized($workspaceId),
            'missingCounts' => [
                'category' => (clone $baseQuery())->whereNull('category_id')->count(),
                'operation' => (clone $baseQuery())->whereNull('operation_id')->count(),
                'company' => (clone $baseQuery())->whereNull('company_id')->count(),
                'funding_source' => (clone $baseQuery())->whereNull('funding_source')->count(),
                'payment_method' => (clone $baseQuery())->whereNull('payment_method')->count(),
            ],
            'estornoPairCount' => $estornoPairPreview['pair_count'],
            'estornoTransactionCount' => $estornoPairPreview['transaction_count'],
        ]);
    }

    public function bulkUpdate(Request $request, TransactionBulkActionService $bulk): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $skip = TransactionBulkActionService::SKIP_VALUE;
        $clear = TransactionBulkActionService::CLEAR_VALUE;

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'category_id' => 'nullable|string',
            'company_id' => 'nullable|string',
            'operation_id' => 'nullable|string',
            'operation_unit_id' => 'nullable|string',
            'status' => 'nullable|string',
            'funding_source' => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        $changes = [];

        if (($validated['category_id'] ?? $skip) !== $skip) {
            if ($validated['category_id'] === $clear) {
                $changes['category_id'] = null;
            } else {
                abort_unless(ctype_digit((string) $validated['category_id']), 422);
                $categoryId = (int) $validated['category_id'];
                abort_unless(
                    Category::query()->where('workspace_id', $workspaceId)->whereKey($categoryId)->exists(),
                    422
                );
                $changes['category_id'] = $categoryId;
            }
        }

        if (($validated['company_id'] ?? $skip) !== $skip) {
            if ($validated['company_id'] === $clear) {
                $changes['company_id'] = null;
            } else {
                abort_unless(ctype_digit((string) $validated['company_id']), 422);
                $companyId = (int) $validated['company_id'];
                abort_unless(
                    Company::query()->where('workspace_id', $workspaceId)->whereKey($companyId)->exists(),
                    422
                );
                $changes['company_id'] = $companyId;
            }
        }

        $operationId = $validated['operation_id'] ?? $skip;
        $operationUnitId = $validated['operation_unit_id'] ?? $skip;
        if ($operationId !== $skip) {
            $changes['operation_id'] = $operationId === $clear ? null : (ctype_digit((string) $operationId) ? (int) $operationId : $operationId);
        }
        if ($operationUnitId !== $skip) {
            $changes['operation_unit_id'] = $operationUnitId === $clear ? null : (ctype_digit((string) $operationUnitId) ? (int) $operationUnitId : $operationUnitId);
        }

        $status = $validated['status'] ?? $skip;
        if ($status !== $skip && $status !== $clear) {
            abort_unless(in_array($status, [
                TransactionStatus::Pending->value,
                TransactionStatus::Confirmed->value,
                TransactionStatus::Cancelled->value,
                TransactionStatus::Reconciled->value,
            ], true), 422);
            $changes['status'] = $status;
        }

        $funding = $validated['funding_source'] ?? $skip;
        if ($funding !== $skip) {
            if ($funding === $clear) {
                $changes['funding_source'] = null;
            } else {
                abort_unless(in_array($funding, array_column(\App\Core\Enums\FundingSource::cases(), 'value'), true), 422);
                $changes['funding_source'] = $funding;
            }
        }

        $payment = $validated['payment_method'] ?? $skip;
        if ($payment !== $skip) {
            if ($payment === $clear) {
                $changes['payment_method'] = null;
            } else {
                abort_unless(in_array($payment, array_column(\App\Core\Enums\PaymentMethod::cases(), 'value'), true), 422);
                $changes['payment_method'] = $payment;
            }
        }

        if ($changes === []) {
            return back()->with(
                'warning',
                'Escolha ao menos um campo diferente de “Não alterar” no formulário em massa.'
            );
        }

        $result = $bulk->apply($workspaceId, $validated['ids'], $changes, $request->user());

        $message = "{$result['updated']} transação(ões) atualizada(s).";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} ignorada(s) (sem permissão).";
        }

        return back()->with('success', $message);
    }

    public function bulkDestroy(Request $request, TransactionBulkActionService $bulk): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'confirm_delete' => 'accepted',
            'delete_confirmation' => 'required|in:EXCLUIR',
            'current_password' => ['required', 'current_password'],
        ], [
            'confirm_delete.accepted' => 'Marque que entende que as transações serão excluídas.',
            'delete_confirmation.in' => 'Digite exatamente EXCLUIR para confirmar.',
        ]);

        $result = $bulk->delete($workspaceId, $validated['ids'], $request->user());

        $message = "{$result['deleted']} transação(ões) excluída(s).";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} ignorada(s).";
        }

        return redirect()
            ->route('transactions.index', app(TransactionIndexFilterService::class)->queryParams($request))
            ->with('success', $message);
    }

    public function removeEstornoPairs(
        Request $request,
        TransactionEstornoPairCleanupService $cleanup,
    ): RedirectResponse {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $result = $cleanup->removeNettedPairs($workspaceId);

        if ($result['removed'] === 0) {
            return back()->with(
                'info',
                'Nenhum par encontrado (compra+estorno no mesmo dia ou estornos duplicados tipo Uber).'
            );
        }

        $purchasePairs = count(array_filter($result['pairs'], fn ($p) => $p['kind'] === 'purchase_estorno'));
        $duplicatePairs = count(array_filter($result['pairs'], fn ($p) => $p['kind'] === 'duplicate_estorno'));

        $parts = [];
        if ($purchasePairs > 0) {
            $parts[] = "{$purchasePairs} compra+estorno";
        }
        if ($duplicatePairs > 0) {
            $parts[] = "{$duplicatePairs} estorno duplicado";
        }

        return back()->with(
            'success',
            'Removidos '.$result['removed'].' lançamentos em '.$result['pair_count'].' par(es): '.implode(', ', $parts).'.'
        );
    }

    public function suggestCategory(Request $request, ReceiptCategorySuggestionService $suggestions): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string|max:500',
            'counterparty' => 'nullable|string|max:255',
            'type' => 'required|in:income,expense,transfer',
        ]);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $type = TransactionType::tryFrom($validated['type']);

        return response()->json([
            'ok' => true,
            'category_suggestion' => $suggestions->forTransaction(
                $workspaceId,
                trim((string) ($validated['description'] ?? '')),
                trim((string) ($validated['counterparty'] ?? '')) ?: null,
                $type,
            ),
        ]);
    }

    public function bulkCategorize(Request $request, BulkCategorizeTransactionsService $service): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $result = $service->run($workspaceId);

        if ($result['total'] === 0) {
            return back()->with('info', 'Não há transações sem categoria.');
        }

        if ($result['categorized'] === 0) {
            return back()->with(
                'warning',
                "Nenhuma das {$result['total']} transações sem categoria correspondeu aos padrões conhecidos (Uber, hotel, débito automático, etc.)."
            );
        }

        $message = "{$result['categorized']} transação(ões) categorizada(s) automaticamente.";
        if ($result['unchanged'] > 0) {
            $message .= " {$result['unchanged']} permanecem sem categoria (sem padrão reconhecido).";
        }

        return back()->with('success', $message);
    }

    public function create(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('finance.transactions.create', [
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'companies' => Company::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'operations' => $this->operationsForForm($workspaceId),
            'frequencies' => RecurrenceFrequency::cases(),
            'preselectedOperationId' => $request->integer('operation_id') ?: null,
            'preselectedUnitId' => $request->integer('operation_unit_id') ?: null,
        ]);
    }

    public function store(Request $request, CreateTransactionAction $action): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'type' => 'required|in:income,expense,transfer',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'transaction_date' => 'required|date',
            'category_id' => 'nullable|integer|exists:categories,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'counterparty' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,confirmed,cancelled,reconciled',
            'is_recurring' => 'sometimes|boolean',
            'recurrence_frequency' => 'nullable|in:weekly,biweekly,monthly,quarterly,yearly',
            'document_id' => 'nullable|integer|exists:documents,id',
            'operation_id' => 'nullable|integer',
            'operation_unit_id' => 'nullable|integer',
        ], $this->paymentFieldRules()));
        $payment = $this->normalizePaymentFields($validated);

        $isRecurring = $request->boolean('is_recurring') && ! empty($validated['recurrence_frequency']);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $operationFields = $this->resolveOperationFields(
            $request,
            $workspaceId,
            isset($validated['company_id']) ? (int) $validated['company_id'] : null,
        );

        $transaction = $action->execute(new CreateTransactionData(
            workspaceId: $workspaceId,
            type: TransactionType::from($validated['type']),
            amount: (float) $validated['amount'],
            description: $validated['description'],
            transactionDate: $validated['transaction_date'],
            categoryId: $validated['category_id'] ?? null,
            companyId: $operationFields['company_id'] ?? ($validated['company_id'] ?? null),
            operationId: $operationFields['operation_id'],
            operationUnitId: $operationFields['operation_unit_id'],
            status: isset($validated['status']) ? TransactionStatus::from($validated['status']) : TransactionStatus::Confirmed,
            counterparty: $validated['counterparty'] ?? null,
            fundingSource: $payment['funding_source'],
            paymentMethod: $payment['payment_method'],
            isRecurring: $isRecurring,
            recurrenceFrequency: $isRecurring
                ? RecurrenceFrequency::from($validated['recurrence_frequency'])
                : null,
        ));

        if (! empty($validated['document_id'])) {
            Document::query()
                ->where('workspace_id', $workspaceId)
                ->whereNull('transaction_id')
                ->whereKey($validated['document_id'])
                ->update(['transaction_id' => $transaction->id]);
        }

        if ($operationFields['operation_id']) {
            return redirect()
                ->route('operations.show', $operationFields['operation_id'])
                ->with('success', 'Lançamento registrado na operação.');
        }

        return redirect()
            ->route('transactions.edit', $transaction)
            ->with('success', 'Transação registrada. Você pode anexar comprovantes abaixo.');
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        $this->authorize('update', $transaction);
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('finance.transactions.edit', [
            'transaction' => $transaction->load(['category', 'company', 'operation', 'operationUnit', 'documents']),
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'companies' => Company::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'operations' => $this->operationsForForm($workspaceId),
            'unlinkedDocuments' => Document::query()
                ->where('workspace_id', $workspaceId)
                ->whereNull('transaction_id')
                ->latest()
                ->limit(30)
                ->get(),
            'requirePassword' => config('financial.security.require_password_for_transaction_sensitive_edit', true),
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $validated = $request->validate(array_merge([
            'description' => 'required|string|max:500',
            'counterparty' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'type' => 'required|in:income,expense,transfer',
            'status' => 'required|in:pending,confirmed,cancelled,reconciled',
            'category_id' => 'nullable|integer|exists:categories,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'operation_id' => 'nullable|integer',
            'operation_unit_id' => 'nullable|integer',
        ], $this->paymentFieldRules()));
        $payment = $this->normalizePaymentFields($validated);

        $operationFields = $this->resolveOperationFields(
            $request,
            (int) $request->attributes->get('workspace_id'),
            isset($validated['company_id']) ? (int) $validated['company_id'] : null,
        );

        if ($this->sensitiveFieldsChanged($request, $transaction)
            && config('financial.security.require_password_for_transaction_sensitive_edit', true)) {
            $request->validate(['current_password' => ['required', 'current_password']]);
        }

        $transaction->update([
            'description' => $validated['description'],
            'counterparty' => $validated['counterparty'] ?? null,
            'funding_source' => $payment['funding_source'],
            'payment_method' => $payment['payment_method'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'type' => $validated['type'],
            'status' => $validated['status'],
            'category_id' => $validated['category_id'] ?? null,
            'company_id' => $operationFields['company_id'] ?? ($validated['company_id'] ?? null),
            'operation_id' => $operationFields['operation_id'],
            'operation_unit_id' => $operationFields['operation_unit_id'],
        ]);

        if ($operationFields['operation_id']) {
            return redirect()
                ->route('operations.show', $operationFields['operation_id'])
                ->with('success', 'Lançamento atualizado.');
        }

        return redirect()
            ->route('transactions.edit', $transaction)
            ->with('success', 'Transação atualizada.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Operation>
     */
    protected function operationsForForm(int $workspaceId)
    {
        return Operation::query()
            ->where('workspace_id', $workspaceId)
            ->with(['activeUnits', 'company'])
            ->orderBy('name')
            ->get();
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $request->validate([
            'confirm_delete' => 'accepted',
            'delete_confirmation' => 'required|in:EXCLUIR',
            'current_password' => ['required', 'current_password'],
        ], [
            'confirm_delete.accepted' => 'Marque que entende que a transação será ocultada.',
            'delete_confirmation.in' => 'Digite exatamente EXCLUIR para confirmar.',
        ]);

        $transaction->delete();

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transação #'.$transaction->id.' excluída (exclusão lógica — pode ser recuperada pelo suporte).');
    }

    protected function sensitiveFieldsChanged(Request $request, Transaction $transaction): bool
    {
        if (DecimalComparer::differs($request->input('amount'), $transaction->amount)) {
            return true;
        }

        if ($request->input('description') !== $transaction->description) {
            return true;
        }

        if ($request->input('type') !== $transaction->type->value) {
            return true;
        }

        if ($request->input('status') !== $transaction->status->value) {
            return true;
        }

        if ($request->input('transaction_date') !== $transaction->transaction_date->format('Y-m-d')) {
            return true;
        }

        return false;
    }
}
