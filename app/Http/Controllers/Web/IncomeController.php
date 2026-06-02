<?php

namespace App\Http\Controllers\Web;

use App\Core\Enums\TransactionType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Services\TransactionIndexFilterService;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;

class IncomeController extends Controller
{
    public function index(Request $request, TransactionIndexFilterService $filters): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', TransactionType::Income)
            ->where(fn ($q) => $q->where('is_recurring', false)->orWhereNull('is_recurring'))
            ->with(['category', 'company', 'operation']);

        $filters->apply($query, $request);

        $perPage = min(100, max(10, $request->integer('per_page', 25)));

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->paginate($perPage)
            ->withQueryString();

        // Totais para os cards de resumo
        $baseUnit = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', TransactionType::Income)
            ->where(fn ($q) => $q->where('is_recurring', false)->orWhereNull('is_recurring'));

        $totalMonth = (clone $baseUnit)
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        $totalYear = (clone $baseUnit)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        return view('finance.income.index', [
            'transactions'   => $transactions,
            'filtersActive'  => $filters->hasActiveFilters($request),
            'totalMonth'     => (float) $totalMonth,
            'totalYear'      => (float) $totalYear,
            'categories'     => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'companies'      => Company::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'operations'     => Operation::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
        ]);
    }
}
