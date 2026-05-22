<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Core\Infrastructure\Models\Workspace;

trait SeedsFinancialWorkspace
{
    protected Workspace $workspace;

    protected User $user;

    protected function seedFinancialWorkspace(): void
    {
        $this->workspace = Workspace::query()->create([
            'name' => 'Test Workspace',
            'slug' => 'test-'.uniqid(),
            'currency' => 'BRL',
        ]);

        $this->user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test-'.uniqid().'@financial.local',
            'password' => Hash::make('password'),
        ]);

        $this->workspace->users()->attach($this->user->id, ['role' => 'owner']);

        Category::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'IA',
            'slug' => 'ia',
            'type' => 'expense',
            'is_system' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    protected function apiHeaders(): array
    {
        return ['X-Workspace-Id' => (string) $this->workspace->id];
    }
}
