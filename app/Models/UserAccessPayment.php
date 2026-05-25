<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class UserAccessPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_profile_id',
        'amount_cents',
        'currency',
        'status',
        'provider',
        'provider_payment_id',
        'paid_at',
        'billing_period_starts_at',
        'billing_period_ends_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'billing_period_starts_at' => 'datetime',
            'billing_period_ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionProfile(): BelongsTo
    {
        return $this->belongsTo(SubscriptionProfile::class);
    }

    public function amountLabel(): string
    {
        return Number::currency($this->amount_cents / 100, $this->currency, 'pt_BR');
    }
}
