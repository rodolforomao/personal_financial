<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;

class FinancialReportService
{
    public function __construct(
        protected TransactionIndexFilterService $filters,
    ) {}

    public function baseQuery(int $workspaceId, Request $request): Builder
    {
        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', [TransactionStatus::Confirmed, TransactionStatus::Reconciled]);

        $this->filters->apply($query, $request);

        return $query;
    }

    /**
     * @return array{
     *     income: float,
     *     expense: float,
     *     net: float,
     *     transaction_count: int,
     *     income_count: int,
     *     expense_count: int
     * }
     */
    public function totals(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('type')
            ->get();

        $income = (float) $rows->where('type', TransactionType::Income->value)->sum('total');
        $expense = (float) $rows->where('type', TransactionType::Expense->value)->sum('total');

        return [
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
            'transaction_count' => (int) $rows->sum('cnt'),
            'income_count' => (int) $rows->where('type', TransactionType::Income->value)->sum('cnt'),
            'expense_count' => (int) $rows->where('type', TransactionType::Expense->value)->sum('cnt'),
        ];
    }

    /**
     * @return list<array{
     *     operation_id: int|null,
     *     label: string,
     *     income: float,
     *     expense: float,
     *     net: float,
     *     count: int
     * }>
     */
    public function byOperation(Builder $query, int $workspaceId): array
    {
        $aggregates = (clone $query)
            ->selectRaw("
                operation_id,
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as expense,
                COUNT(*) as cnt
            ", [TransactionType::Income->value, TransactionType::Expense->value])
            ->groupBy('operation_id')
            ->orderByDesc('income')
            ->get();

        $operationNames = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $aggregates->pluck('operation_id')->filter()->all())
            ->pluck('name', 'id');

        return $aggregates->map(function ($row) use ($operationNames) {
            $income = round((float) $row->income, 2);
            $expense = round((float) $row->expense, 2);
            $operationId = $row->operation_id ? (int) $row->operation_id : null;

            return [
                'operation_id' => $operationId,
                'label' => $operationId
                    ? ($operationNames[$operationId] ?? 'Operação #'.$operationId)
                    : 'Sem operação',
                'income' => $income,
                'expense' => $expense,
                'net' => round($income - $expense, 2),
                'count' => (int) $row->cnt,
            ];
        })->sortByDesc(fn (array $row) => abs($row['net']))->values()->all();
    }

    /**
     * @return list<array{
     *     category_id: int|null,
     *     label: string,
     *     income: float,
     *     expense: float,
     *     net: float,
     *     count: int
     * }>
     */
    public function byCategory(Builder $query): array
    {
        $aggregates = (clone $query)
            ->selectRaw("
                category_id,
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as expense,
                COUNT(*) as cnt
            ", [TransactionType::Income->value, TransactionType::Expense->value])
            ->groupBy('category_id')
            ->get();

        $categoryNames = Category::query()
            ->whereIn('id', $aggregates->pluck('category_id')->filter()->all())
            ->pluck('name', 'id');

        return $aggregates->map(function ($row) use ($categoryNames) {
            $income = round((float) $row->income, 2);
            $expense = round((float) $row->expense, 2);
            $categoryId = $row->category_id ? (int) $row->category_id : null;

            return [
                'category_id' => $categoryId,
                'label' => $categoryId
                    ? ($categoryNames[$categoryId] ?? 'Categoria #'.$categoryId)
                    : 'Sem categoria',
                'income' => $income,
                'expense' => $expense,
                'net' => round($income - $expense, 2),
                'count' => (int) $row->cnt,
            ];
        })->sortByDesc('expense')->values()->all();
    }

    /**
     * @return list<array{
     *     company_id: int|null,
     *     label: string,
     *     income: float,
     *     expense: float,
     *     net: float,
     *     count: int
     * }>
     */
    public function byCompany(Builder $query): array
    {
        $aggregates = (clone $query)
            ->selectRaw("
                company_id,
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as expense,
                COUNT(*) as cnt
            ", [TransactionType::Income->value, TransactionType::Expense->value])
            ->groupBy('company_id')
            ->get();

        $companyNames = Company::query()
            ->whereIn('id', $aggregates->pluck('company_id')->filter()->all())
            ->pluck('name', 'id');

        return $aggregates->map(function ($row) use ($companyNames) {
            $income = round((float) $row->income, 2);
            $expense = round((float) $row->expense, 2);
            $companyId = $row->company_id ? (int) $row->company_id : null;

            return [
                'company_id' => $companyId,
                'label' => $companyId
                    ? ($companyNames[$companyId] ?? 'Empresa #'.$companyId)
                    : 'Sem empresa',
                'income' => $income,
                'expense' => $expense,
                'net' => round($income - $expense, 2),
                'count' => (int) $row->cnt,
            ];
        })->sortByDesc('expense')->values()->all();
    }

    public function periodLabel(Request $request): string
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->format('d/m/Y')
            : null;
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->format('d/m/Y')
            : null;

        if ($from && $to) {
            return "{$from} — {$to}";
        }

        if ($from) {
            return "A partir de {$from}";
        }

        if ($to) {
            return "Até {$to}";
        }

        return 'Todo o período (lançamentos confirmados e conciliados)';
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $workspaceId, Request $request): array
    {
        $query = $this->baseQuery($workspaceId, $request);

        return [
            'totals' => $this->totals($query),
            'by_operation' => $this->byOperation($query, $workspaceId),
            'by_category' => $this->byCategory($query),
            'by_company' => $this->byCompany($query),
            'period_label' => $this->periodLabel($request),
            'transaction_list_url' => route('transactions.index', $this->filters->queryParams($request)),
        ];
    }
}
