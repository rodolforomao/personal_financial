<?php

namespace Modules\Finance\Infrastructure\Models;

use App\Core\Enums\FundingSource;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\RecurrenceFrequency;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Application\DTOs\DashboardFilter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\OCR\Infrastructure\Models\Document;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Operations\Infrastructure\Models\OperationUnit;
use Modules\Projects\Infrastructure\Models\Project;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Transaction extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'financial_account_id', 'category_id', 'company_id',
        'project_id', 'operation_id', 'operation_unit_id', 'linked_transaction_id',
        'type', 'status', 'amount', 'currency', 'description',
        'counterparty', 'funding_source', 'payment_method', 'transaction_date', 'due_date', 'paid_at',
        'is_recurring', 'recurring_item_id', 'recurrence_frequency',
        'source', 'external_id', 'metadata', 'categorization_confidence',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'is_recurring' => 'boolean',
            'recurrence_frequency' => RecurrenceFrequency::class,
            'metadata' => 'array',
            'categorization_confidence' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->logFillable();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function operationUnit(): BelongsTo
    {
        return $this->belongsTo(OperationUnit::class);
    }

    /** Transação espelhada (contraparte double-entry). */
    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'linked_transaction_id');
    }

    /** Visão consolidada: sem operação ou operações marcadas para o dashboard principal. */
    public function scopeForMainDashboard(Builder $query): Builder
    {
        return $query->forDashboard(DashboardFilter::consolidated());
    }

    public function scopeForDashboard(Builder $query, ?DashboardFilter $filter = null): Builder
    {
        $filter ??= DashboardFilter::consolidated();
        $excludeIds = $filter->normalizedExcludeIds();

        if ($filter->includeAllOperations) {
            if ($excludeIds !== []) {
                $query->where(function (Builder $q) use ($excludeIds) {
                    $q->whereNull('operation_id')
                        ->orWhereNotIn('operation_id', $excludeIds);
                });
            }

            return $query;
        }

        $query->where(function (Builder $q) {
            $q->whereNull('operation_id')
                ->orWhereHas('operation', fn (Builder $op) => $op->where('exclude_from_main_dashboard', false));
        });

        if ($excludeIds !== []) {
            $query->where(function (Builder $q) use ($excludeIds) {
                $q->whereNull('operation_id')
                    ->orWhereNotIn('operation_id', $excludeIds);
            });
        }

        return $query;
    }

    public function recurringItem(): BelongsTo
    {
        return $this->belongsTo(RecurringItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function fundingSourceLabel(): ?string
    {
        return FundingSource::tryFrom($this->funding_source ?? '')?->label();
    }

    public function paymentMethodLabel(): ?string
    {
        return PaymentMethod::tryFrom($this->payment_method ?? '')?->label();
    }

    public function isCltSalary(): bool
    {
        return $this->source === 'clt_salary';
    }

    /** Rótulo da coluna Recorrência (CLT = mensal implícito). */
    public function recurrenceColumnLabel(): ?string
    {
        if ($this->isCltSalary()) {
            return 'Mensal';
        }

        if ($this->is_recurring && $this->recurrence_frequency) {
            return $this->recurrence_frequency->label();
        }

        return null;
    }
}
