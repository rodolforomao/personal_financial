<?php

namespace Tests\Feature\Phase1;

use App\Models\SubscriptionProfile;
use App\Models\User;
use App\Models\UserAccessPayment;
use Modules\Core\Infrastructure\Models\Workspace;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccessManagementTest extends TestCase
{
    public function test_registration_creates_pending_user_until_payment_or_admin_release(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'phone' => '11999999999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('subscription.pending'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'cliente@example.com')->firstOrFail();

        $this->assertSame(User::ACCESS_PENDING_PAYMENT, $user->access_status);
        $this->assertFalse($user->hasActivePlatformAccess());
        $this->assertNotNull($user->subscription_profile_id);
        $this->assertDatabaseHas('workspace_user', ['user_id' => $user->id, 'role' => 'owner']);

        $this->get(route('dashboard'))->assertRedirect(route('subscription.pending'));
    }

    public function test_admin_can_release_specific_user_manually(): void
    {
        [$admin, $profile] = $this->adminAndProfile();
        $user = User::factory()->create([
            'subscription_profile_id' => $profile->id,
            'access_status' => User::ACCESS_PENDING_PAYMENT,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update-access', $user), [
                'subscription_profile_id' => $profile->id,
                'access_status' => User::ACCESS_MANUAL_RELEASE,
                'is_platform_internal' => 0,
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame(User::ACCESS_MANUAL_RELEASE, $user->access_status);
        $this->assertTrue($user->hasActivePlatformAccess());
        $this->assertSame($admin->id, $user->access_approved_by);
    }

    public function test_confirmed_payment_activates_user_access_for_profile_price(): void
    {
        [$admin, $profile] = $this->adminAndProfile();
        $user = User::factory()->create([
            'subscription_profile_id' => $profile->id,
            'access_status' => User::ACCESS_PENDING_PAYMENT,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.confirm-payment', $user), [
                'subscription_profile_id' => $profile->id,
                'months' => 1,
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $payment = UserAccessPayment::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(User::ACCESS_ACTIVE, $user->access_status);
        $this->assertTrue($user->hasActivePlatformAccess());
        $this->assertSame(2000, $payment->amount_cents);
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($user->access_expires_at);
    }

    /**
     * @return array{0: User, 1: SubscriptionProfile}
     */
    private function adminAndProfile(): array
    {
        $profile = SubscriptionProfile::query()->create([
            'name' => 'Mensal',
            'slug' => 'mensal',
            'monthly_price_cents' => 2000,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'is_platform_internal' => true,
            'subscription_profile_id' => $profile->id,
            'access_status' => User::ACCESS_MANUAL_RELEASE,
        ]);

        Workspace::query()
            ->create(['name' => 'Admin', 'slug' => 'admin', 'currency' => 'BRL'])
            ->users()
            ->attach($admin->id, ['role' => 'owner']);

        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        return [$admin, $profile];
    }
}
