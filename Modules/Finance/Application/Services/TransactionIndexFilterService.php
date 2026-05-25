<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\FundingSource;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Finance\Infrastructure\Models\Transaction;

class TransactionIndexFilterService
{
    /** @var list<string> */
    public const QUERY_KEYS = [
        'description',
        'counterparty',
        'date_from',
        'date_to',
        'category_id',
        'company_id',
        'operation_id',
        'operation_unit_id',
        'type',
        'status',
        'funding_source',
        'payment_method',
        'source',
        'amount_min',
        'amount_max',
        'missing',
        'per_page',
    ];

    public function apply(Builder $query, Request $request): Builder
    {
        if ($request->filled('description')) {
            $term = $this->likeTerm((string) $request->input('description'));
            $query->where('description', 'like', '%'.$term.'%');
        }

        if ($request->filled('counterparty')) {
            $term = $this->likeTerm((string) $request->input('counterparty'));
            $query->where('counterparty', 'like', '%'.$term.'%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('operation_id')) {
            $query->where('operation_id', $request->integer('operation_id'));
        }

        if ($request->filled('operation_unit_id')) {
            $query->where('operation_unit_id', $request->integer('operation_unit_id'));
        }

        if ($request->filled('type')) {
            $type = TransactionType::tryFrom((string) $request->input('type'));
            if ($type) {
                $query->where('type', $type);
            }
        }

        if ($request->filled('status')) {
            $status = TransactionStatus::tryFrom((string) $request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('funding_source')) {
            $source = FundingSource::tryFrom((string) $request->input('funding_source'));
            if ($source) {
                $query->where('funding_source', $source->value);
            }
        }

        if ($request->filled('payment_method')) {
            $method = PaymentMethod::tryFrom((string) $request->input('payment_method'));
            if ($method) {
                $query->where('payment_method', $method->value);
            }
        }

        if ($request->filled('source')) {
            $query->where('source', (string) $request->input('source'));
        }

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', (float) $request->input('amount_min'));
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', (float) $request->input('amount_max'));
        }

        if ($request->filled('missing')) {
            match ($request->input('missing')) {
                'category' => $query->whereNull('category_id'),
                'operation' => $query->whereNull('operation_id'),
                'company' => $query->whereNull('company_id'),
                'funding_source' => $query->whereNull('funding_source'),
                'payment_method' => $query->whereNull('payment_method'),
                default => null,
            };
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryParams(Request $request): array
    {
        return array_filter(
            $request->only(self::QUERY_KEYS),
            fn ($value) => $value !== null && $value !== '',
        );
    }

    public function hasActiveFilters(Request $request): bool
    {
        foreach (self::QUERY_KEYS as $key) {
            if ($key === 'per_page') {
                continue;
            }
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function knownSources(int $workspaceId): array
    {
        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('source')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->all();
    }

    protected function likeTerm(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], trim($value));
    }
}
