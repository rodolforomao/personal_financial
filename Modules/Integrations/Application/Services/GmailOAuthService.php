<?php

namespace Modules\Integrations\Application\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integrations\Infrastructure\Models\IntegrationConnection;

class GmailOAuthService
{
    public const PROVIDER = 'gmail';

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function connection(int $workspaceId, ?int $userId = null): ?IntegrationConnection
    {
        $query = IntegrationConnection::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', self::PROVIDER);

        $userId === null
            ? $query->whereNull('user_id')
            : $query->where('user_id', $userId);

        return $query->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, IntegrationConnection>
     */
    public function connectionsForWorkspace(int $workspaceId)
    {
        return IntegrationConnection::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', self::PROVIDER)
            ->whereNotNull('user_id')
            ->get();
    }

    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/userinfo.email',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    /**
     * @return array{email: ?string, token: array<string, mixed>}
     */
    public function exchangeCode(string $code): array
    {
        $token = Http::asForm()
            ->timeout(20)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ])
            ->throw()
            ->json();

        $email = $this->fetchEmail((string) ($token['access_token'] ?? ''));

        return ['email' => $email, 'token' => $token];
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function storeConnection(int $workspaceId, int $userId, array $token, ?string $email): IntegrationConnection
    {
        $credentials = [
            'access_token' => $token['access_token'] ?? null,
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_type' => $token['token_type'] ?? 'Bearer',
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))->toIso8601String(),
            'scope' => $token['scope'] ?? null,
            'email' => $email,
        ];

        return IntegrationConnection::query()->updateOrCreate(
            ['workspace_id' => $workspaceId, 'provider' => self::PROVIDER, 'user_id' => $userId],
            [
                'status' => 'active',
                'credentials' => $credentials,
                'settings' => [
                    'connected_by' => $userId,
                    'email' => $email,
                    'processed_message_ids' => [],
                ],
                'last_error' => null,
            ],
        );
    }

    public function accessToken(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials ?? [];
        $access = (string) ($credentials['access_token'] ?? '');
        $expiresAt = isset($credentials['expires_at']) ? strtotime((string) $credentials['expires_at']) : 0;

        if ($access !== '' && $expiresAt > now()->addMinute()->timestamp) {
            return $access;
        }

        return $this->refreshAccessToken($connection);
    }

    public function disconnect(int $workspaceId, int $userId): void
    {
        $this->connection($workspaceId, $userId)?->delete();
    }

    protected function refreshAccessToken(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials ?? [];
        $refresh = (string) ($credentials['refresh_token'] ?? '');
        if ($refresh === '') {
            throw new \RuntimeException('Gmail sem refresh_token. Reconecte a conta.');
        }

        $token = Http::asForm()
            ->timeout(20)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ])
            ->throw()
            ->json();

        $credentials['access_token'] = $token['access_token'] ?? null;
        $credentials['expires_at'] = now()->addSeconds((int) ($token['expires_in'] ?? 3600))->toIso8601String();
        $credentials['token_type'] = $token['token_type'] ?? ($credentials['token_type'] ?? 'Bearer');

        $connection->update([
            'credentials' => $credentials,
            'status' => 'active',
            'last_error' => null,
        ]);

        return (string) ($credentials['access_token'] ?? '');
    }

    protected function fetchEmail(string $accessToken): ?string
    {
        if ($accessToken === '') {
            return null;
        }

        try {
            $profile = Http::withToken($accessToken)
                ->timeout(15)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo')
                ->throw()
                ->json();

            return isset($profile['email']) ? Str::lower((string) $profile['email']) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function clientId(): string
    {
        return (string) config('financial.integrations.gmail.client_id');
    }

    protected function clientSecret(): string
    {
        return (string) config('financial.integrations.gmail.client_secret');
    }

    protected function redirectUri(): string
    {
        $configured = (string) config('financial.integrations.gmail.redirect_uri');

        return $configured !== '' ? $configured : route('integrations.gmail.callback');
    }
}
