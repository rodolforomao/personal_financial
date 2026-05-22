<?php

namespace Modules\Integrations\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Infrastructure\Models\Workspace;

class WebhookLog extends Model
{
    protected $fillable = [
        'workspace_id',
        'provider',
        'event',
        'payload',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
