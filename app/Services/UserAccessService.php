<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccessPayment;
use Illuminate\Support\Carbon;

class UserAccessService
{
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
