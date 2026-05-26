<?php

namespace Modules\Categorization\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Infrastructure\Models\Workspace;

class CategorizationRuleAssignment extends Model
{
    protected $fillable = [
        'categorization_rule_id', 'workspace_id', 'category_id', 'priority', 'is_active', 'hit_count',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CategorizationRule::class, 'categorization_rule_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
