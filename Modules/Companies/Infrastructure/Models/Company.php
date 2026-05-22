<?php

namespace Modules\Companies\Infrastructure\Models;

use App\Core\Enums\CompanyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Infrastructure\Models\Workspace;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'name', 'type', 'partnership_share', 'document', 'email', 'phone',
        'status', 'expected_monthly_revenue', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'partnership_share' => 'decimal:2',
            'expected_monthly_revenue' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(CompanyContract::class);
    }
}
