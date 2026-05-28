<?php

namespace App\Services;

use App\Models\SubscriptionProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Infrastructure\Models\Workspace;

class WorkspaceUserInviteService
{
    public function __construct(
        private readonly UserAccessService $accessService,
    ) {}

    /**
     * @return array{user: User, created: bool, attached: bool, reset_link_sent: bool}
     */
    public function invite(
        string $email,
        int $workspaceId,
        string $role = 'member',
        ?string $name = null,
        ?User $invitedBy = null,
        bool $sendResetLink = true,
        bool $grantPlatformAccess = false,
    ): array {
        $workspace = Workspace::query()->findOrFail($workspaceId);
        $email = Str::lower(trim($email));

        return DB::transaction(function () use (
            $email,
            $workspace,
            $role,
            $name,
            $invitedBy,
            $sendResetLink,
            $grantPlatformAccess,
        ): array {
            $user = User::query()->where('email', $email)->first();
            $created = false;
            $resetLinkSent = false;

            if (! $user) {
                $user = User::query()->create([
                    'name' => $name ?: Str::before($email, '@'),
                    'email' => $email,
                    'password' => Str::password(32),
                    'subscription_profile_id' => $this->defaultSubscriptionProfile()->id,
                    'access_status' => User::ACCESS_PENDING_PAYMENT,
                ]);
                $created = true;
            }

            $alreadyMember = $user->workspaces()
                ->where('workspaces.id', $workspace->id)
                ->exists();

            if ($alreadyMember) {
                throw ValidationException::withMessages([
                    'email' => 'Este usuário já pertence ao workspace selecionado.',
                ]);
            }

            $user->workspaces()->attach($workspace->id, ['role' => $role]);

            if ($grantPlatformAccess && $invitedBy) {
                $this->accessService->grantManualAccess(
                    $user,
                    $invitedBy,
                    now()->addYear(),
                );
            }

            if ($created && $sendResetLink) {
                $status = Password::sendResetLink(['email' => $user->email]);
                $resetLinkSent = $status === Password::RESET_LINK_SENT;
            }

            return [
                'user' => $user->fresh(['workspaces']),
                'created' => $created,
                'attached' => true,
                'reset_link_sent' => $resetLinkSent,
            ];
        });
    }

    private function defaultSubscriptionProfile(): SubscriptionProfile
    {
        return SubscriptionProfile::query()->firstOrCreate(
            ['slug' => 'mensal'],
            [
                'name' => 'Mensal',
                'monthly_price_cents' => 2000,
                'description' => 'Acesso padrão ao Financial IQ.',
                'is_active' => true,
            ]
        );
    }
}
