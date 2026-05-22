<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Application\Services\AiCredentialsResolver;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class AiPlatformAccessTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
        config(['financial.ai.openai.api_key' => 'sk-test-key']);
        config(['financial.ai.system_enabled' => true]);
        Role::findOrCreate('admin');
    }

    public function test_internal_user_may_use_platform_ai_without_acceptance(): void
    {
        $user = User::factory()->create([
            'is_platform_internal' => true,
            'preferences' => ['ai' => ['mode' => 'system', 'provider' => 'openai', 'model' => 'gpt-4o-mini']],
        ]);

        $resolver = app(AiCredentialsResolver::class);

        $this->assertTrue($resolver->userMayUsePlatformAi($user));
        $creds = $resolver->resolve($user->id, $this->workspace->id);
        $this->assertSame('system_internal', $creds->source);
        $this->assertFalse($creds->isBillable);
    }

    public function test_external_user_requires_billing_acceptance_for_platform_ai(): void
    {
        $user = User::factory()->create([
            'is_platform_internal' => false,
            'preferences' => ['ai' => ['mode' => 'system', 'provider' => 'openai', 'model' => 'gpt-4o-mini']],
        ]);

        $resolver = app(AiCredentialsResolver::class);

        $this->assertFalse($resolver->userMayUsePlatformAi($user));

        $user->forceFill([
            'preferences' => [
                'ai' => [
                    'mode' => 'system',
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'platform_ai_accepted_at' => now()->toIso8601String(),
                ],
            ],
        ])->save();

        $creds = $resolver->resolve($user->id, $this->workspace->id);
        $this->assertSame('system', $creds->source);
        $this->assertTrue($creds->isBillable);
    }
}
