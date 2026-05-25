<?php

namespace Modules\Operations\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Finance\Infrastructure\Models\Transaction;

class OperationUnit extends Model
{
    protected $fillable = [
        'operation_id',
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function displayName(): string
    {
        if ($this->code) {
            return "{$this->name} ({$this->code})";
        }

        return $this->name;
    }
}
