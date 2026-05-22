<?php

namespace Modules\Finance\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Infrastructure\Models\Workspace;

class CashFlowSnapshot extends Model
{
    protected $fillable = [
        'workspace_id', 'snapshot_date', 'total_income', 'total_expense',
        'net_cash_flow', 'balance', 'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_income' => 'decimal:2',
            'total_expense' => 'decimal:2',
            'net_cash_flow' => 'decimal:2',
            'balance' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
