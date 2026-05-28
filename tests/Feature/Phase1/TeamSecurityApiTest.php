<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use App\Services\SecurityAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Infrastructure\Models\WorkspaceInvite;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class TeamSecurityApiTest extends TestCase
{
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['users.invite', 'sessions.view', 'sessions.revoke', 'tokens.manage', 'audit.view'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $role = Role::findOrCreate('TENANT_OWNER', 'web');
        $role->syncPermissions(Permission::all());

        $this->seedFinancialWorkspace();
        $this->user->assignRole('TENANT_OWNER');
    }

    public function test_team_invite_create_list_accept_and_revoke(): void
    {
        $response = $this->postJson('/api/v1/team/invite', [
            'email' => 'invited@tenant.local',
            'role' => 'member',
        ], $this->apiHeaders());

        $response->assertCreated()
            ->assertJsonPath('invite.email', 'invited@tenant.local');

        $token = $response->json('invite.token');

        $this->getJson('/api/v1/team/invites', $this->apiHeaders())
            ->assertOk()
            ->assertJsonFragment(['email' => 'invited@tenant.local']);

        $this->postJson('/api/v1/team/invite/accept', [
            'token' => $token,
            'name' => 'Convidado',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk()
            ->assertJsonPath('user.email', 'invited@tenant.local');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $this->workspace->id,
            'role' => 'member',
        ]);

        $invite = WorkspaceInvite::query()->where('token', $token)->first();
        $this->assertNotNull($invite->accepted_at);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => SecurityAuditLogger::LOG_NAME,
            'event' => 'invite.sent',
        ]);
    }

    public function test_sessions_list_and_revoke(): void
    {
        DB::table('sessions')->insert([
            'id' => 'test-session-id',
            'user_id' => $this->user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode(''),
            'last_activity' => now()->timestamp,
        ]);

        $this->getJson('/api/v1/team/sessions', $this->apiHeaders())
            ->assertOk()
            ->assertJsonFragment(['id' => 'test-session-id']);

        $this->deleteJson('/api/v1/team/sessions/test-session-id', [], $this->apiHeaders())
            ->assertNoContent();

        $this->assertDatabaseMissing('sessions', ['id' => 'test-session-id']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => SecurityAuditLogger::LOG_NAME,
            'event' => 'session.revoked',
        ]);
    }

    public function test_api_tokens_create_list_and_revoke(): void
    {
        $create = $this->postJson('/api/v1/team/tokens', [
            'name' => 'ci-token',
        ], $this->apiHeaders());

        $create->assertCreated()
            ->assertJsonPath('access_token.name', 'ci-token')
            ->assertJsonStructure(['token']);

        $tokenId = $create->json('access_token.id');

        $this->getJson('/api/v1/team/tokens', $this->apiHeaders())
            ->assertOk()
            ->assertJsonFragment(['name' => 'ci-token']);

        $this->deleteJson("/api/v1/team/tokens/{$tokenId}", [], $this->apiHeaders())
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => SecurityAuditLogger::LOG_NAME,
            'event' => 'token.created',
        ]);
    }

    public function test_login_logout_writes_security_audit(): void
    {
        $user = User::query()->create([
            'name' => 'Audit User',
            'email' => 'audit-'.uniqid().'@financial.local',
            'password' => Hash::make('password'),
            'access_status' => User::ACCESS_MANUAL_RELEASE,
            'access_expires_at' => now()->addYear(),
        ]);
        $this->workspace->users()->attach($user->id, ['role' => 'member']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], $this->apiHeaders())->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => SecurityAuditLogger::LOG_NAME,
            'event' => 'login',
            'causer_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout', [], $this->apiHeaders())->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => SecurityAuditLogger::LOG_NAME,
            'event' => 'logout',
            'causer_id' => $user->id,
        ]);
    }

    public function test_audit_endpoint_lists_security_events(): void
    {
        Activity::query()->create([
            'log_name' => SecurityAuditLogger::LOG_NAME,
            'description' => 'Teste',
            'event' => 'login',
            'properties' => ['workspace_id' => $this->workspace->id],
            'causer_id' => $this->user->id,
            'causer_type' => User::class,
        ]);

        $this->getJson('/api/v1/team/audit?scope=security', $this->apiHeaders())
            ->assertOk()
            ->assertJsonFragment(['event' => 'login']);
    }
}
