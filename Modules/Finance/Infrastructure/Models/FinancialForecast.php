<?php

namespace Modules\Finance\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Infrastructure\Models\Workspace;

class FinancialForecast extends Model
{
    protected $table = 'financial_forecasts';

    protected $fillable = [
        'workspace_id', 'forecast_date', 'horizon_days',
        'projected_income', 'projected_expense', 'projected_balance',
        'risk_level', 'details',
    ];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'projected_income' => 'decimal:2',
            'projected_expense' => 'decimal:2',
            'projected_balance' => 'decimal:2',
            'details' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
