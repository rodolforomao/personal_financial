<?php

namespace Modules\Categorization\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Operations\Infrastructure\Models\Operation;

class CategorizationRule extends Model
{
    protected $fillable = [
        'workspace_id', 'category_id', 'operation_id', 'company_id',
        'name', 'match_type', 'pattern',
        'transaction_type', 'priority', 'is_active', 'hit_count',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CategorizationRuleAssignment::class);
    }
}
