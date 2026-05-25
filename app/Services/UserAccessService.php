<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccessPayment;
use Illuminate\Support\Carbon;

class UserAccessService
{
    public function syncPaymentAccess(User $user): User
    {
        if ($user->is_platform_internal || $user->access_status === User::ACCESS_BLOCKED) {
            return $user;
        }

        if ($user->access_status === User::ACCESS_MANUAL_RELEASE) {
            if ($user->access_expires_at !== null && $user->access_expires_at->isPast()) {
                $this->markPendingPayment($user);

                return $user->refresh();
            }

            return $user;
        }

        $currentPayment = $user->accessPayments()
            ->where('status', 'paid')
            ->where(function ($query): void {
                $query->whereNull('billing_period_ends_at')
                    ->orWhere('billing_period_ends_at', '>', now());
            })
            ->latest('billing_period_ends_at')
            ->first();

        if ($currentPayment) {
            $expiresChanged = $user->access_expires_at?->toDateTimeString()
                !== $currentPayment->billing_period_ends_at?->toDateTimeString();

            if ($user->access_status !== User::ACCESS_ACTIVE
                || $expiresChanged
                || $user->subscription_profile_id !== $currentPayment->subscription_profile_id
            ) {
                $user->forceFill([
                    'subscription_profile_id' => $currentPayment->subscription_profile_id,
                    'access_status' => User::ACCESS_ACTIVE,
                    'access_approved_at' => $currentPayment->paid_at ?? now(),
                    'access_approved_by' => null,
                    'last_payment_at' => $currentPayment->paid_at ?? $user->last_payment_at,
                    'access_expires_at' => $currentPayment->billing_period_ends_at,
                ])->save();
            }

            return $user->refresh();
        }

        if ($user->access_status === User::ACCESS_ACTIVE) {
            $this->markPendingPayment($user);

            return $user->refresh();
        }

        return $user;
    }

    public function grantManualAccess(User $user, ?User $approver = null, ?Carbon $expiresAt = null): void
    {
        $user->forceFill([
            'access_status' => User::ACCESS_MANUAL_RELEASE,
            'access_approved_at' => now(),
            'access_approved_by' => $approver?->id,
            'access_expires_at' => $expiresAt,
        ])->save();
    }

    public function blockAccess(User $user): void
    {
        $user->forceFill([
            'access_status' => User::ACCESS_BLOCKED,
            'access_expires_at' => now(),
        ])->save();
    }

    public function markPendingPayment(User $user): void
    {
        $user->forceFill([
            'access_status' => User::ACCESS_PENDING_PAYMENT,
            'access_approved_at' => null,
            'access_approved_by' => null,
            'access_expires_at' => null,
        ])->save();
    }

    public function markPaymentAsPaid(UserAccessPayment $payment, ?Carbon $paidAt = null): void
    {
        $paidAt ??= now();

        $payment->forceFill([
            'status' => 'paid',
            'paid_at' => $paidAt,
        ])->save();

        $payment->user->forceFill([
            'subscription_profile_id' => $payment->subscription_profile_id,
            'access_status' => User::ACCESS_ACTIVE,
            'access_approved_at' => $paidAt,
            'access_approved_by' => null,
            'last_payment_at' => $paidAt,
            'access_expires_at' => $payment->billing_period_ends_at,
        ])->save();
    }
}
