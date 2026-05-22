<?php

namespace Modules\Intelligence\Infrastructure\Models;

use App\Core\Enums\AlertSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Infrastructure\Models\Workspace;

class AiInsight extends Model
{
    protected $fillable = [
        'workspace_id', 'type', 'severity', 'title', 'summary',
        'payload', 'suggested_actions', 'provider', 'is_read',
        'is_resolved', 'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'payload' => 'array',
            'suggested_actions' => 'array',
            'is_read' => 'boolean',
            'is_resolved' => 'boolean',
            'detected_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
