<?php

namespace Modules\Finance\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Core\Infrastructure\Models\Workspace;

class CltSalary extends Model
{
    protected $fillable = [
        'workspace_id', 'company_id', 'category_id', 'gross_amount', 'net_amount',
        'payment_day', 'description_template', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'payment_day' => 'integer',
            'is_active' => 'boolean',
        ];
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

    public function periods(): HasMany
    {
        return $this->hasMany(CltSalaryPeriod::class);
    }
}
