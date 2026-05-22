<?php

namespace Modules\Alerts\Infrastructure\Models;

use App\Core\Enums\AlertSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Infrastructure\Models\Workspace;

class Alert extends Model
{
    protected $fillable = [
        'workspace_id', 'type', 'severity', 'title', 'message',
        'context', 'is_read', 'is_sent', 'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'context' => 'array',
            'is_read' => 'boolean',
            'is_sent' => 'boolean',
            'triggered_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
