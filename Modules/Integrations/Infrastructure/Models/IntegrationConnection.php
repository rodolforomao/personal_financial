<?php

namespace Modules\Integrations\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Infrastructure\Models\Workspace;

class IntegrationConnection extends Model
{
    protected $fillable = [
        'workspace_id', 'provider', 'status', 'credentials',
        'settings', 'last_sync_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'last_sync_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
