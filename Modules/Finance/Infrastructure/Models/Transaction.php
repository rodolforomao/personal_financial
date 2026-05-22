<?php

namespace Modules\Finance\Infrastructure\Models;

use App\Core\Enums\RecurrenceFrequency;
use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Projects\Infrastructure\Models\Project;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Transaction extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'financial_account_id', 'category_id', 'company_id',
        'project_id', 'type', 'status', 'amount', 'currency', 'description',
        'counterparty', 'transaction_date', 'due_date', 'paid_at',
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

    public function recurringItem(): BelongsTo
    {
        return $this->belongsTo(RecurringItem::class);
    }
}
