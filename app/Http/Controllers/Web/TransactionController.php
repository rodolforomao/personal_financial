<?php

namespace App\Http\Controllers\Web;

use App\Core\Enums\RecurrenceFrequency;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Infrastructure\Models\Transaction;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->with(['category', 'company', 'recurringItem']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return view('finance.transactions.index', [
            'transactions' => $query->orderByDesc('transaction_date')->paginate(20)->withQueryString(),
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('finance.transactions.create', [
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'companies' => Company::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'frequencies' => RecurrenceFrequency::cases(),
        ]);
    }

    public function store(Request $request, CreateTransactionAction $action): RedirectResponse
    {
        $validated = $request->validate([
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
        ]);

        $isRecurring = $request->boolean('is_recurring') && ! empty($validated['recurrence_frequency']);

        $action->execute(new CreateTransactionData(
            workspaceId: (int) $request->attributes->get('workspace_id'),
            type: TransactionType::from($validated['type']),
            amount: (float) $validated['amount'],
            description: $validated['description'],
            transactionDate: $validated['transaction_date'],
            categoryId: $validated['category_id'] ?? null,
            companyId: $validated['company_id'] ?? null,
            status: isset($validated['status']) ? TransactionStatus::from($validated['status']) : TransactionStatus::Confirmed,
            counterparty: $validated['counterparty'] ?? null,
            isRecurring: $isRecurring,
            recurrenceFrequency: $isRecurring
                ? RecurrenceFrequency::from($validated['recurrence_frequency'])
                : null,
        ));

        return redirect()->route('transactions.index')->with('success', 'Transação registrada.');
    }
}
