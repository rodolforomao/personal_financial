<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use Modules\Core\Infrastructure\Models\Workspace;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApiUsersTenantIsolationTest extends TestCase
{
    public function test_users_list_is_scoped_by_workspace_header(): void
    {
        $actor = User::factory()->create();
        $workspaceA = Workspace::query()->create(['name' => 'A', 'slug' => 'a', 'currency' => 'BRL']);
        $workspaceB = Workspace::query()->create(['name' => 'B', 'slug' => 'b', 'currency' => 'BRL']);

        $userA = User::factory()->create(['email' => 'a@tenant.local']);
        $userB = User::factory()->create(['email' => 'b@tenant.local']);

        $workspaceA->users()->attach($actor->id, ['role' => 'owner']);
        $workspaceA->users()->attach($userA->id, ['role' => 'member']);
        $workspaceB->users()->attach($userB->id, ['role' => 'member']);

        $actor->givePermissionTo(Permission::findOrCreate('users.view', 'web'));

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/users', ['X-Workspace-Id' => (string) $workspaceA->id])
            ->assertOk()
            ->assertJsonFragment(['email' => 'a@tenant.local'])
            ->assertJsonMissing(['email' => 'b@tenant.local']);
    }
}
