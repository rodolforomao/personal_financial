<?php

namespace Tests\Feature\Phase1;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
{
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_users_index_requires_users_view_permission(): void
    {
        $this->getJson('/api/v1/users', $this->apiHeaders())
            ->assertForbidden();

        $this->user->givePermissionTo(
            Permission::findOrCreate('users.view', 'web'),
        );

        $this->getJson('/api/v1/users', $this->apiHeaders())
            ->assertOk();
    }

    public function test_team_invite_requires_users_invite_permission(): void
    {
        $this->postJson('/api/v1/team/invite', [
            'email' => 'invited@tenant.local',
        ], $this->apiHeaders())
            ->assertForbidden();

        $this->user->givePermissionTo(
            Permission::findOrCreate('users.invite', 'web'),
        );

        $this->postJson('/api/v1/team/invite', [
            'email' => 'invited@tenant.local',
        ], $this->apiHeaders())
            ->assertCreated()
            ->assertJsonPath('invite.email', 'invited@tenant.local');
    }

    public function test_permissions_index_requires_permissions_view(): void
    {
        Permission::findOrCreate('users.view', 'web');
        Permission::findOrCreate('audit.view', 'web');
        Permission::findOrCreate('permissions.view', 'web');

        $this->getJson('/api/v1/permissions', $this->apiHeaders())
            ->assertForbidden();

        $this->user->givePermissionTo('permissions.view');

        $response = $this->getJson('/api/v1/permissions', $this->apiHeaders())
            ->assertOk();

        $names = $response->json('data');
        $this->assertContains('users.view', $names);
        $this->assertContains('audit.view', $names);
    }

    public function test_roles_index_requires_roles_view(): void
    {
        Role::findOrCreate('VIEWER', 'web');

        $this->getJson('/api/v1/roles', $this->apiHeaders())
            ->assertForbidden();

        $this->user->givePermissionTo(Permission::findOrCreate('roles.view', 'web'));

        $this->getJson('/api/v1/roles', $this->apiHeaders())
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_team_audit_requires_audit_view(): void
    {
        $this->getJson('/api/v1/team/audit', $this->apiHeaders())
            ->assertForbidden();

        $this->user->givePermissionTo(Permission::findOrCreate('audit.view', 'web'));

        $this->getJson('/api/v1/team/audit', $this->apiHeaders())
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page']);
    }

    public function test_team_sessions_requires_sessions_view(): void
    {
        $this->getJson('/api/v1/team/sessions', $this->apiHeaders())
            ->assertForbidden();

        $this->user->givePermissionTo(Permission::findOrCreate('sessions.view', 'web'));

        $this->getJson('/api/v1/team/sessions', $this->apiHeaders())
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_team_tokens_requires_tokens_manage(): void
    {
        $this->getJson('/api/v1/team/tokens', $this->apiHeaders())
            ->assertForbidden();

        $this->user->givePermissionTo(Permission::findOrCreate('tokens.manage', 'web'));

        $this->getJson('/api/v1/team/tokens', $this->apiHeaders())
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_cannot_delete_self(): void
    {
        $this->user->givePermissionTo([
            Permission::findOrCreate('users.view', 'web'),
            Permission::findOrCreate('users.delete', 'web'),
        ]);

        $this->deleteJson('/api/v1/users/'.$this->user->id, [], $this->apiHeaders())
            ->assertForbidden();
    }
}
