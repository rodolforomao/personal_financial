<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Core\Infrastructure\Models\Workspace;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'preferences',
    'is_platform_internal',
    'subscription_profile_id',
    'access_status',
    'access_approved_at',
    'access_approved_by',
    'last_payment_at',
    'access_expires_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable
{
    public const ACCESS_PENDING_PAYMENT = 'pending_payment';

    public const ACCESS_ACTIVE = 'active';

    public const ACCESS_MANUAL_RELEASE = 'manual_release';

    public const ACCESS_BLOCKED = 'blocked';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'is_platform_internal' => 'boolean',
            'preferences' => 'array',
            'access_approved_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'access_expires_at' => 'datetime',
        ];
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function subscriptionProfile(): BelongsTo
    {
        return $this->belongsTo(SubscriptionProfile::class);
    }

    public function accessApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'access_approved_by');
    }

    public function accessPayments(): HasMany
    {
        return $this->hasMany(UserAccessPayment::class);
    }

    public function latestAccessPayment(): HasOne
    {
        return $this->hasOne(UserAccessPayment::class)->latestOfMany();
    }

    public function hasActivePlatformAccess(): bool
    {
        if ($this->is_platform_internal || $this->hasRole('admin')) {
            return true;
        }

        if ($this->access_status === self::ACCESS_MANUAL_RELEASE) {
            return $this->access_expires_at === null || $this->access_expires_at->isFuture();
        }

        if ($this->access_status === self::ACCESS_ACTIVE) {
            return $this->access_expires_at !== null && $this->access_expires_at->isFuture();
        }

        return false;
    }

    public function accessStatusLabel(): string
    {
        return match ($this->access_status) {
            self::ACCESS_ACTIVE => 'Ativo por pagamento',
            self::ACCESS_MANUAL_RELEASE => 'Liberado manualmente',
            self::ACCESS_BLOCKED => 'Bloqueado',
            default => 'Aguardando pagamento',
        };
    }
}
