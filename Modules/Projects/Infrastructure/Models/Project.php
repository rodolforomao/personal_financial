<?php

namespace Modules\Projects\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Core\Infrastructure\Models\Workspace;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'company_id', 'name', 'status', 'budget',
        'expected_return', 'total_cost', 'total_revenue',
        'starts_at', 'ends_at', 'description',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'expected_return' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function roi(): float
    {
        if ($this->total_cost <= 0) {
            return 0;
        }

        return (($this->total_revenue - $this->total_cost) / $this->total_cost) * 100;
    }
}
