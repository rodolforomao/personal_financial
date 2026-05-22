<?php

namespace Modules\Finance\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Infrastructure\Models\Workspace;

class Asset extends Model
{
    protected $fillable = [
        'workspace_id', 'name', 'type', 'current_value',
        'acquisition_value', 'acquired_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:2',
            'acquisition_value' => 'decimal:2',
            'acquired_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
