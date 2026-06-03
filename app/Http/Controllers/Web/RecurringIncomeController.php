<?php

namespace App\Http\Controllers\Web;

use App\Core\Enums\RecurrenceFrequency;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Core\Support\IndexValueService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Infrastructure\Models\RecurringItem;

class RecurringIncomeController extends Controller
{
    public function __construct(
        private readonly IndexValueService $indexService,
    ) {}

    public function index(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $items = RecurringItem::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'income')
            ->with(['category', 'company'])
            ->orderBy('next_due_at')
            ->get();

        // Calcula valor atual para itens indexados
        $calculated = [];
        foreach ($items as $item) {
            if ($item->hasIndexedAmount()) {
                try {
                    $calculated[$item->id] = $this->indexService->calculate($item);
                } catch (\Throwable) {
                    $calculated[$item->id] = null;
                }
            }
        }

        $categories = Category::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'income')
            ->orderBy('name')
            ->get();

        $companies = Company::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get();

        return view('finance.recurring-income.index', [
            'items'       => $items,
            'calculated'  => $calculated,
            'categories'  => $categories,
            'companies'   => $companies,
            'indexLabels' => IndexValueService::AVAILABLE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'amount'        => 'required|numeric|min:0.01',
            'amount_index'  => 'nullable|string|in:'.implode(',', array_keys(IndexValueService::AVAILABLE)),
            'amount_factor' => 'nullable|numeric|min:0.000001',
            'day_of_month'  => 'nullable|integer|min:1|max:28',
            'next_due_at'   => 'required|date',
            'category_id'   => 'nullable|integer|exists:categories,id',
            'company_id'    => 'nullable|integer|exists:companies,id',
            'alert_enabled' => 'nullable|boolean',
        ]);

        RecurringItem::query()->create([
            'workspace_id'  => $workspaceId,
            'type'          => 'income',
            'frequency'     => 'monthly',
            'title'         => $validated['title'],
            'amount'        => $validated['amount'],
            'amount_index'  => $validated['amount_index'] ?? null,
            'amount_factor' => isset($validated['amount_factor']) ? (float) $validated['amount_factor'] : null,
            'day_of_month'  => $validated['day_of_month'] ?? null,
            'next_due_at'   => $validated['next_due_at'],
            'category_id'   => $validated['category_id'] ?? null,
            'company_id'    => $validated['company_id'] ?? null,
            'alert_enabled' => $request->boolean('alert_enabled', true),
            'is_active'     => true,
        ]);

        return redirect()->route('recurring-income.index')->with('success', 'Receita recorrente cadastrada.');
    }

    public function update(Request $request, RecurringItem $recurringIncome): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($recurringIncome->workspace_id === $workspaceId, 404);

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'amount'        => 'required|numeric|min:0.01',
            'amount_index'  => 'nullable|string|in:'.implode(',', array_keys(IndexValueService::AVAILABLE)),
            'amount_factor' => 'nullable|numeric|min:0.000001',
            'day_of_month'  => 'nullable|integer|min:1|max:28',
            'next_due_at'   => 'required|date',
            'category_id'   => 'nullable|integer|exists:categories,id',
            'company_id'    => 'nullable|integer|exists:companies,id',
            'alert_enabled' => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ]);

        $recurringIncome->update([
            'title'         => $validated['title'],
            'amount'        => $validated['amount'],
            'amount_index'  => $validated['amount_index'] ?? null,
            'amount_factor' => isset($validated['amount_factor']) ? (float) $validated['amount_factor'] : null,
            'day_of_month'  => $validated['day_of_month'] ?? null,
            'next_due_at'   => $validated['next_due_at'],
            'category_id'   => $validated['category_id'] ?? null,
            'company_id'    => $validated['company_id'] ?? null,
            'alert_enabled' => $request->boolean('alert_enabled', true),
            'is_active'     => $request->boolean('is_active', true),
        ]);

        return redirect()->route('recurring-income.index')->with('success', 'Receita recorrente atualizada.');
    }

    public function markReceived(
        Request $request,
        RecurringItem $recurringIncome,
        CreateTransactionAction $createTransaction,
    ): RedirectResponse {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($recurringIncome->workspace_id === $workspaceId, 404);

        $nextDue = $recurringIncome->next_due_at->addMonthNoOverflow();
        if ($recurringIncome->day_of_month) {
            $nextDue = $nextDue->setDay(min($recurringIncome->day_of_month, $nextDue->daysInMonth));
        }

        // ── Itens indexados: calcula o valor e cria transação ─────────────────
        if ($recurringIncome->hasIndexedAmount()) {
            $request->validate([
                'amount'           => 'required|numeric|min:0.01',
                'transaction_date' => 'nullable|date',
            ]);

            $amount      = (float) $request->input('amount');
            $txDate      = $request->input('transaction_date') ?: now()->toDateString();
            $indexResult = $this->safeCalculate($recurringIncome);

            $metadata = [
                'recurring_item_id' => $recurringIncome->id,
                'amount_index'      => $recurringIncome->amount_index,
                'amount_factor'     => (float) $recurringIncome->amount_factor,
            ];
            if ($indexResult) {
                $metadata['index_value']   = $indexResult['index_value'];
                $metadata['index_label']   = $indexResult['index_label'];
                $metadata['index_formula'] = $indexResult['formula'];
                $metadata['index_source']  = $indexResult['source'];
            }

            $transaction = $createTransaction->execute(new CreateTransactionData(
                workspaceId:         $workspaceId,
                type:                TransactionType::Income,
                amount:              $amount,
                description:         $recurringIncome->title,
                transactionDate:     $txDate,
                categoryId:          $recurringIncome->category_id,
                companyId:           $recurringIncome->company_id,
                counterparty:        $recurringIncome->company?->name,
                status:              TransactionStatus::Confirmed,
                source:              'manual',
                metadata:            $metadata,
                isRecurring:         true,
                recurrenceFrequency: RecurrenceFrequency::Monthly,
            ));

            // Vincula a transação ao item recorrente
            $transaction->update(['recurring_item_id' => $recurringIncome->id]);

            // Atualiza o valor de referência do item com o calculado
            $recurringIncome->update([
                'amount'             => $amount,
                'last_occurrence_at' => $txDate,
                'next_due_at'        => $nextDue->toDateString(),
            ]);

            return redirect()
                ->route('transactions.edit', $transaction)
                ->with('success', "Receita \"{$recurringIncome->title}\" confirmada (R$ ".number_format($amount, 2, ',', '.')."). Próxima: {$nextDue->format('d/m/Y')}.");
        }

        // ── Itens com valor fixo: comportamento original ───────────────────────
        $recurringIncome->update([
            'last_occurrence_at' => now()->toDateString(),
            'next_due_at'        => $nextDue->toDateString(),
        ]);

        return redirect()
            ->route('recurring-income.index')
            ->with('success', "Receita \"{$recurringIncome->title}\" marcada como recebida. Próxima: {$nextDue->format('d/m/Y')}.");
    }

    public function destroy(Request $request, RecurringItem $recurringIncome): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($recurringIncome->workspace_id === $workspaceId, 404);

        $recurringIncome->delete();

        return redirect()->route('recurring-income.index')->with('success', 'Receita recorrente removida.');
    }

    private function safeCalculate(RecurringItem $item): ?array
    {
        try {
            return $this->indexService->calculate($item);
        } catch (\Throwable) {
            return null;
        }
    }
}
