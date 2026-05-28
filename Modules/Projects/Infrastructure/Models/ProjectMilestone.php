<?php

namespace Modules\Projects\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'weight_percent',
        'sort_order',
        'is_completed',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_percent' => 'decimal:2',
            'is_completed' => 'boolean',
            'due_at' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
