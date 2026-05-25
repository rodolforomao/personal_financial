<?php

namespace Modules\Finance\Infrastructure\Models;

use App\Core\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatementLine extends Model
{
    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_SKIPPED = 'skipped';

    /** Compra+estorno ou estornos duplicados no mesmo dia — oculta na conciliação. */
    public const STATUS_NETTED = 'netted';

    protected $fillable = [
        'statement_import_id', 'transaction_id', 'transaction_date', 'amount',
        'type', 'description', 'counterparty', 'external_ref',
        'match_status', 'match_score',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'amount' => 'decimal:2',
            'type' => TransactionType::class,
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class, 'statement_import_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('match_status', '!=', self::STATUS_NETTED);
    }
}
