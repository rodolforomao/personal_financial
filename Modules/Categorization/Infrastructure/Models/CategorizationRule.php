<?php

namespace Modules\Categorization\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Infrastructure\Models\Workspace;

class CategorizationRule extends Model
{
    protected $fillable = [
        'workspace_id', 'category_id', 'match_type', 'pattern',
        'priority', 'is_active', 'hit_count',
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
}
