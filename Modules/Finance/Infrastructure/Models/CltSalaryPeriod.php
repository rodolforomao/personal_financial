<?php

namespace Modules\Finance\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CltSalaryPeriod extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'clt_salary_id', 'reference_month', 'gross_amount', 'net_amount', 'status',
        'transaction_id', 'confirmed_at', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'gross_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function cltSalary(): BelongsTo
    {
        return $this->belongsTo(CltSalary::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
