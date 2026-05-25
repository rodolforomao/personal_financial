<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;

class SubscriptionProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'monthly_price_cents',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(UserAccessPayment::class);
    }

    public function monthlyPriceLabel(): string
    {
        return Number::currency($this->monthly_price_cents / 100, 'BRL', 'pt_BR');
    }
}
