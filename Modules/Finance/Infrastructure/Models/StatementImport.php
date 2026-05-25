<?php

namespace Modules\Finance\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Infrastructure\Models\Workspace;

class StatementImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_IMPORTED = 'imported';

    protected $fillable = [
        'workspace_id', 'user_id', 'original_name', 'format', 'status',
        'lines_total', 'matched_count', 'imported_count', 'netted_count', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StatementLine::class);
    }

    public function refreshCounts(): void
    {
        $this->update([
            'lines_total' => $this->lines()->visible()->count(),
            'matched_count' => $this->lines()->where('match_status', StatementLine::STATUS_MATCHED)->count(),
            'imported_count' => $this->lines()->where('match_status', StatementLine::STATUS_IMPORTED)->count(),
            'netted_count' => $this->lines()->where('match_status', StatementLine::STATUS_NETTED)->count(),
        ]);
    }
}
