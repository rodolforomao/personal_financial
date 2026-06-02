<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Infrastructure\Models\RecurringItem;

class RecurringIncomeController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $items = RecurringItem::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'income')
            ->with(['category', 'company'])
            ->orderBy('next_due_at')
            ->get();

        $categories = Category::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'income')
            ->orderBy('name')
            ->get();

        $companies = Company::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get();

        return view('finance.recurring-income.index', compact('items', 'categories', 'companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'next_due_at' => 'required|date',
            'category_id' => 'nullable|integer|exists:categories,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'alert_enabled' => 'nullable|boolean',
        ]);

        RecurringItem::query()->create([
            'workspace_id' => $workspaceId,
            'type' => 'income',
            'frequency' => 'monthly',
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'day_of_month' => $validated['day_of_month'] ?? null,
            'next_due_at' => $validated['next_due_at'],
            'category_id' => $validated['category_id'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'alert_enabled' => $request->boolean('alert_enabled', true),
            'is_active' => true,
        ]);

        return redirect()->route('recurring-income.index')->with('success', 'Receita recorrente cadastrada.');
    }

    public function update(Request $request, RecurringItem $recurringIncome): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($recurringIncome->workspace_id === $workspaceId, 404);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'next_due_at' => 'required|date',
            'category_id' => 'nullable|integer|exists:categories,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'alert_enabled' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $recurringIncome->update([
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'day_of_month' => $validated['day_of_month'] ?? null,
            'next_due_at' => $validated['next_due_at'],
            'category_id' => $validated['category_id'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'alert_enabled' => $request->boolean('alert_enabled', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('recurring-income.index')->with('success', 'Receita recorrente atualizada.');
    }

    public function markReceived(Request $request, RecurringItem $recurringIncome): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($recurringIncome->workspace_id === $workspaceId, 404);

        $nextDue = $recurringIncome->next_due_at->addMonthNoOverflow();

        if ($recurringIncome->day_of_month) {
            $nextDue = $nextDue->setDay(min($recurringIncome->day_of_month, $nextDue->daysInMonth));
        }

        $recurringIncome->update([
            'last_occurrence_at' => now()->toDateString(),
            'next_due_at' => $nextDue->toDateString(),
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
}
