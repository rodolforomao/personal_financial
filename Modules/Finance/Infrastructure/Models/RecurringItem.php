<?php

namespace Modules\Finance\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Core\Infrastructure\Models\Workspace;

class RecurringItem extends Model
{
    protected $fillable = [
        'workspace_id', 'category_id', 'company_id', 'type', 'title', 'amount',
        'amount_index', 'amount_factor',
        'frequency', 'day_of_month', 'next_due_at', 'last_occurrence_at',
        'is_active', 'alert_enabled',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'amount_factor' => 'decimal:6',
            'next_due_at'   => 'date',
            'last_occurrence_at' => 'date',
            'is_active'     => 'boolean',
            'alert_enabled' => 'boolean',
        ];
    }

    public function hasIndexedAmount(): bool
    {
        return $this->amount_index !== null && $this->amount_factor !== null;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
