<?php

namespace Modules\Operations\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Finance\Infrastructure\Models\Transaction;

class Operation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'company_id',
        'name',
        'slug',
        'description',
        'exclude_from_main_dashboard',
        'partners_count',
        'total_invested',
    ];

    protected function casts(): array
    {
        return [
            'exclude_from_main_dashboard' => 'boolean',
            'partners_count' => 'integer',
            'total_invested' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Operation $operation) {
            if (empty($operation->slug)) {
                $operation->slug = Str::slug($operation->name);
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(OperationUnit::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeUnits(): HasMany
    {
        return $this->units()->where('is_active', true);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
