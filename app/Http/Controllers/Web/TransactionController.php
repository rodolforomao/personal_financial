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
use Illuminate\Support\Facades\DB;
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
use Modules\Finance\Infrastructure\Models\RecurringItem;
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
            ->with(['category', 'company', 'operation', 'operationUnit', 'recurringItem', 'linkedTransaction.operation'])
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

        $companyIdsWithOperation = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('company_id')
            ->pluck('company_id');

        $operationsToAssignCount = $companyIdsWithOperation->isNotEmpty()
            ? (clone $baseQuery())->whereNull('operation_id')->whereNotNull('company_id')->whereIn('company_id', $companyIdsWithOperation)->count()
            : 0;

        return view('finance.transactions.index', [
            'transactions' => $query->orderByDesc('transaction_date')->paginate($perPage)->withQueryString(),
            'filtersActive' => $filters->hasActiveFilters($request),
            'knownSources' => $filters->knownSources($workspaceId),
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'companies' => Company::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'operations' => $operations,
            'operationUnits' => $operationUnits,
            'uncategorizedCount' => $bulkCategorize->countUncategorized($workspaceId),
            'operationsToAssignCount' => $operationsToAssignCount,
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
        if (! empty($result['operations_assigned']) && $result['operations_assigned'] > 0) {
            $message .= " {$result['operations_assigned']} transação(ões) vinculada(s) à operação via empresa.";
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

        $type = TransactionType::from($validated['type']);
        $status = isset($validated['status']) ? TransactionStatus::from($validated['status']) : TransactionStatus::Confirmed;
        $companyId = $operationFields['company_id'] ?? ($validated['company_id'] ?? null);

        $mirrorPersonal = $request->boolean('mirror_personal_capital')
            && $operationFields['operation_id']
            && $type === TransactionType::Income;

        $transaction = DB::transaction(function () use (
            $action, $workspaceId, $type, $status, $companyId, $validated, $operationFields,
            $payment, $isRecurring, $mirrorPersonal,
        ) {
            $tx = $action->execute(new CreateTransactionData(
                workspaceId: $workspaceId,
                type: $type,
                amount: (float) $validated['amount'],
                description: $validated['description'],
                transactionDate: $validated['transaction_date'],
                categoryId: $validated['category_id'] ?? null,
                companyId: $companyId,
                operationId: $operationFields['operation_id'],
                operationUnitId: $operationFields['operation_unit_id'],
                status: $status,
                counterparty: $validated['counterparty'] ?? null,
                fundingSource: $payment['funding_source'],
                paymentMethod: $payment['payment_method'],
                isRecurring: $isRecurring,
                recurrenceFrequency: $isRecurring
                    ? RecurrenceFrequency::from($validated['recurrence_frequency'])
                    : null,
            ));

            if ($mirrorPersonal) {
                $mirror = $action->execute(new CreateTransactionData(
                    workspaceId: $workspaceId,
                    type: TransactionType::Expense,
                    amount: (float) $validated['amount'],
                    description: $validated['description'],
                    transactionDate: $validated['transaction_date'],
                    categoryId: $validated['category_id'] ?? null,
                    companyId: $companyId,
                    operationId: null,
                    operationUnitId: null,
                    status: $status,
                    counterparty: $validated['counterparty'] ?? null,
                    fundingSource: $payment['funding_source'],
                    paymentMethod: $payment['payment_method'],
                    source: 'mirror',
                ));
                $tx->update(['linked_transaction_id' => $mirror->id]);
                $mirror->update(['linked_transaction_id' => $tx->id]);
            }

            return $tx;
        });

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
                ->with('success', $transaction->linked_transaction_id
                    ? 'Lançamento registrado na operação e saída espelhada no capital pessoal.'
                    : 'Lançamento registrado na operação.');
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
            'transaction' => $transaction->load(['category', 'company', 'operation', 'operationUnit', 'documents', 'linkedTransaction', 'recurringItem.company']),
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

        DB::transaction(function () use ($transaction, $validated, $payment, $operationFields) {
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

            if ($transaction->linked_transaction_id) {
                Transaction::withoutTimestamps(function () use ($transaction, $validated) {
                    Transaction::query()
                        ->whereKey($transaction->linked_transaction_id)
                        ->update([
                            'description' => $validated['description'],
                            'amount' => $validated['amount'],
                            'transaction_date' => $validated['transaction_date'],
                            'counterparty' => $validated['counterparty'] ?? null,
                        ]);
                });
            }
        });

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

    public function createPersonalMirror(Request $request, Transaction $transaction, CreateTransactionAction $action): RedirectResponse
    {
        $this->authorize('update', $transaction);

        abort_if($transaction->linked_transaction_id, 422, 'Este lançamento já possui uma contraparte.');
        abort_if(! $transaction->operation_id, 422, 'Apenas lançamentos de operação podem ter espelho no capital pessoal.');
        abort_if($transaction->type !== TransactionType::Income, 422, 'Apenas receitas de operação podem ser espelhadas como saída pessoal.');

        DB::transaction(function () use ($transaction, $action) {
            $mirror = $action->execute(new CreateTransactionData(
                workspaceId: $transaction->workspace_id,
                type: TransactionType::Expense,
                amount: (float) $transaction->amount,
                description: $transaction->description,
                transactionDate: $transaction->transaction_date->format('Y-m-d'),
                categoryId: $transaction->category_id,
                companyId: $transaction->company_id,
                operationId: null,
                operationUnitId: null,
                status: $transaction->status,
                counterparty: $transaction->counterparty,
                fundingSource: $transaction->funding_source,
                paymentMethod: $transaction->payment_method,
                source: 'mirror',
            ));
            $transaction->update(['linked_transaction_id' => $mirror->id]);
            $mirror->update(['linked_transaction_id' => $transaction->id]);
        });

        $backRoute = $transaction->operation_id
            ? route('operations.show', $transaction->operation_id)
            : route('transactions.edit', $transaction);

        return redirect($backRoute)
            ->with('success', 'Saída no capital pessoal criada e vinculada ao lançamento da operação.');
    }

    public function duplicate(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $new = $transaction->replicate([
            'linked_transaction_id',  // não copiar vínculo double-entry
            'recurring_item_id',      // não copiar vínculo recorrente (cada ocorrência é independente)
            'external_id',            // ID externo é único por origem
            'metadata',
            'categorization_confidence',
            'source',
            'paid_at',                // novo lançamento ainda não foi pago
        ]);

        $new->transaction_date = now()->toDateString();
        $new->status = TransactionStatus::Pending;
        $new->save();

        return redirect()
            ->route('transactions.edit', $new)
            ->with('duplicated_from', $transaction->id);
    }

    public function bulkMarkRecurring(Request $request): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $alertEnabled = $request->boolean('alert_enabled', true);

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        $transactions = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $validated['ids'])
            ->where('type', TransactionType::Income)
            ->whereNull('recurring_item_id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($transactions as $tx) {
            $dayOfMonth = min($tx->transaction_date->day, 28);
            $nextDue = $tx->transaction_date->addMonthNoOverflow();
            if ($dayOfMonth) {
                $nextDue = $nextDue->setDay(min($dayOfMonth, $nextDue->daysInMonth));
            }

            $item = RecurringItem::query()->create([
                'workspace_id' => $tx->workspace_id,
                'type' => 'income',
                'frequency' => 'monthly',
                'title' => $tx->description,
                'amount' => $tx->amount,
                'day_of_month' => $dayOfMonth,
                'next_due_at' => $nextDue->toDateString(),
                'last_occurrence_at' => $tx->transaction_date->toDateString(),
                'category_id' => $tx->category_id,
                'company_id' => $tx->company_id,
                'alert_enabled' => $alertEnabled,
                'is_active' => true,
            ]);

            $tx->update([
                'recurring_item_id' => $item->id,
                'is_recurring' => true,
                'recurrence_frequency' => RecurrenceFrequency::Monthly->value,
            ]);

            $created++;
        }

        $notIncome = count($validated['ids']) - $created - $skipped;
        $message = "{$created} receita(s) marcada(s) como recorrente.";
        if ($notIncome > 0) {
            $message .= " {$notIncome} ignorada(s) (não são receitas ou já vinculadas).";
        }

        return back()->with('success', $message);
    }

    public function createRecurring(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);
        abort_if($transaction->type !== TransactionType::Income, 422, 'Apenas receitas podem ser marcadas como recorrentes.');
        abort_if($transaction->recurring_item_id, 422, 'Esta transação já está vinculada a uma receita recorrente.');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'next_due_at' => 'required|date',
            'alert_enabled' => 'nullable|boolean',
        ]);

        $item = RecurringItem::query()->create([
            'workspace_id' => $transaction->workspace_id,
            'type' => 'income',
            'frequency' => 'monthly',
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'day_of_month' => $validated['day_of_month'] ?? $transaction->transaction_date->day,
            'next_due_at' => $validated['next_due_at'],
            'last_occurrence_at' => $transaction->transaction_date->toDateString(),
            'category_id' => $transaction->category_id,
            'company_id' => $transaction->company_id,
            'alert_enabled' => $request->boolean('alert_enabled', true),
            'is_active' => true,
        ]);

        $transaction->update([
            'recurring_item_id' => $item->id,
            'is_recurring' => true,
            'recurrence_frequency' => RecurrenceFrequency::Monthly->value,
        ]);

        return redirect()
            ->route('transactions.edit', $transaction)
            ->with('success', "Receita recorrente \"{$item->title}\" criada e vinculada a este lançamento.");
    }

    public function unlinkRecurring(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $transaction->update([
            'recurring_item_id' => null,
            'is_recurring' => false,
            'recurrence_frequency' => null,
        ]);

        return redirect()
            ->route('transactions.edit', $transaction)
            ->with('success', 'Vínculo com receita recorrente removido deste lançamento.');
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

        DB::transaction(function () use ($transaction) {
            $mirrorId = $transaction->linked_transaction_id;
            $transaction->delete();

            if ($mirrorId) {
                Transaction::query()->whereKey($mirrorId)->first()?->delete();
            }
        });

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transação #'.$transaction->id.' excluída'
                .($transaction->linked_transaction_id ? ' (e sua contraparte do capital pessoal)' : '')
                .' — exclusão lógica.');
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
