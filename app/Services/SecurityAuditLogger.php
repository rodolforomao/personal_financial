<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SecurityAuditLogger
{
    public const LOG_NAME = 'security';

    public function log(
        string $event,
        string $description,
        ?User $causer = null,
        ?Model $subject = null,
        array $properties = [],
    ): void {
        $logger = activity(self::LOG_NAME)
            ->event($event)
            ->withProperties($properties);

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $logger->log($description);
    }

    public function login(User $user, array $context = []): void
    {
        $this->log('login', 'Login realizado.', $user, $user, $context);
    }

    public function logout(User $user, array $context = []): void
    {
        $this->log('logout', 'Logout realizado.', $user, $user, $context);
    }

    public function inviteSent(User $actor, Model $invite, array $context = []): void
    {
        $this->log('invite.sent', 'Convite de workspace enviado.', $actor, $invite, $context);
    }

    public function inviteAccepted(User $user, Model $invite, array $context = []): void
    {
        $this->log('invite.accepted', 'Convite de workspace aceito.', $user, $invite, $context);
    }

    public function inviteRevoked(User $actor, Model $invite, array $context = []): void
    {
        $this->log('invite.revoked', 'Convite de workspace revogado.', $actor, $invite, $context);
    }

    public function sessionRevoked(User $actor, array $context = []): void
    {
        $this->log('session.revoked', 'Sessão revogada.', $actor, null, $context);
    }

    public function tokenCreated(User $user, Model $token, array $context = []): void
    {
        $this->log('token.created', 'Token de API criado.', $user, $token, $context);
    }

    public function tokenRevoked(User $user, Model $token, array $context = []): void
    {
        $this->log('token.revoked', 'Token de API revogado.', $user, $token, $context);
    }
}
