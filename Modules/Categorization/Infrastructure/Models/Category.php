<?php

namespace Modules\Categorization\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Infrastructure\Models\Workspace;

class Category extends Model
{
    protected $fillable = [
        'workspace_id', 'parent_id', 'name', 'slug', 'type', 'color', 'icon', 'is_system',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CategorizationRule::class);
    }
}
