<?php

namespace Tests\Feature\Phase1;

use App\Models\SubscriptionProfile;
use App\Models\User;
use App\Models\UserAccessPayment;
use Illuminate\Support\Facades\Http;
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

    public function test_pending_user_can_generate_liquidx_pix_qr_code(): void
    {
        $this->configureLiquidx();
        $profile = $this->profile();
        /** @var User $user */
        $user = User::factory()->create([
            'subscription_profile_id' => $profile->id,
            'access_status' => User::ACCESS_PENDING_PAYMENT,
            'phone' => '11999999999',
        ]);

        Http::fake([
            'liquidx.test/api/integrated-payment' => function () {
                return Http::response([
                    'pix' => [
                        'success' => true,
                        'data' => [
                            'response' => [
                                'id' => 'depix-test-001',
                                'qrCopyPaste' => '000201TESTPIX',
                                'qrImageUrl' => 'https://response.eulen.app/api-response/test-qr.png',
                            ],
                            'async' => false,
                        ],
                    ],
                    'reais' => '20.00',
                    'depixfees' => '0.00',
                ]);
            },
        ]);

        $this->actingAs($user)
            ->post(route('subscription.payment.store'))
            ->assertRedirect(route('subscription.pending'));

        $payment = UserAccessPayment::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('pending', $payment->status);
        $this->assertSame('liquidx', $payment->provider);
        $this->assertSame('depix-test-001', $payment->provider_payment_id);
        $this->assertSame('000201TESTPIX', data_get($payment->metadata, 'liquidx.qr_code'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://liquidx.test/api/integrated-payment'
            && $request['code'] === 'integrated-code'
            && $request['value'] === '20.00');

        $this->actingAs($user)
            ->get(route('subscription.pending'))
            ->assertOk()
            ->assertSee('Já paguei, verificar status')
            ->assertSee('000201TESTPIX');

        $this->actingAs($user)
            ->get(route('subscription.payment.qr-code', $payment))
            ->assertOk()
            ->assertSee('<svg', false);
    }

    public function test_liquidx_paid_status_activates_user_access(): void
    {
        $this->configureLiquidx();
        $profile = $this->profile();
        /** @var User $user */
        $user = User::factory()->create([
            'subscription_profile_id' => $profile->id,
            'access_status' => User::ACCESS_PENDING_PAYMENT,
        ]);
        $payment = UserAccessPayment::query()->create([
            'user_id' => $user->id,
            'subscription_profile_id' => $profile->id,
            'amount_cents' => 2000,
            'currency' => 'BRL',
            'status' => 'pending',
            'provider' => 'liquidx',
            'provider_payment_id' => 'depix-paid-test',
            'billing_period_starts_at' => now(),
            'billing_period_ends_at' => now()->addMonthNoOverflow(),
            'metadata' => [
                'liquidx' => [
                    'qr_code' => '000201TESTPIX',
                    'status' => 'pending',
                ],
            ],
        ]);

        Http::fake([
            'liquidx.test/api/integrated-payment/status' => Http::response([
                [
                    'depix_id' => 'depix-paid-test',
                    'status' => 'paid',
                    'date' => now()->format('Y-m-d H:i:s'),
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('subscription.payment.check'))
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $payment->refresh();

        $this->assertSame(User::ACCESS_ACTIVE, $user->access_status);
        $this->assertTrue($user->hasActivePlatformAccess());
        $this->assertSame('paid', $payment->status);
    }

    public function test_liquidx_webhook_can_activate_user_access(): void
    {
        $this->configureLiquidx(['financial.billing.liquidx.webhook_secret' => 'secret-test']);
        $profile = $this->profile();
        $user = User::factory()->create([
            'subscription_profile_id' => $profile->id,
            'access_status' => User::ACCESS_PENDING_PAYMENT,
        ]);
        $payment = UserAccessPayment::query()->create([
            'user_id' => $user->id,
            'subscription_profile_id' => $profile->id,
            'amount_cents' => 2000,
            'currency' => 'BRL',
            'status' => 'pending',
            'provider' => 'liquidx',
            'provider_payment_id' => 'depix-webhook-test',
            'billing_period_starts_at' => now(),
            'billing_period_ends_at' => now()->addMonthNoOverflow(),
        ]);

        $this->postJson(route('webhooks.liquidx.payments', ['secret' => 'secret-test']), [
            'depix_id' => 'depix-webhook-test',
            'status' => 'depix_sent',
            'date' => now()->format('Y-m-d H:i:s'),
        ])->assertOk()
            ->assertJson(['ok' => true, 'payment_id' => $payment->id]);

        $user->refresh();
        $payment->refresh();

        $this->assertSame(User::ACCESS_ACTIVE, $user->access_status);
        $this->assertSame('paid', $payment->status);
    }

    /**
     * @return array{0: User, 1: SubscriptionProfile}
     */
    private function adminAndProfile(): array
    {
        $profile = $this->profile();

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

    private function profile(): SubscriptionProfile
    {
        return SubscriptionProfile::query()->create([
            'name' => 'Mensal',
            'slug' => 'mensal',
            'monthly_price_cents' => 2000,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureLiquidx(array $overrides = []): void
    {
        config(array_merge([
            'financial.billing.liquidx.base_url' => 'https://liquidx.test/api',
            'financial.billing.liquidx.api_key' => 'api-key',
            'financial.billing.liquidx.integrated_code' => 'integrated-code',
            'financial.billing.liquidx.integrated_payment_path' => '/integrated-payment',
            'financial.billing.liquidx.integrated_payment_status_path' => '/integrated-payment/status',
            'financial.billing.liquidx.thirdwallet' => null,
            'financial.billing.liquidx.webhook_secret' => null,
            'financial.billing.liquidx.timeout' => 20,
            'financial.billing.liquidx.default_payer_phone' => null,
        ], $overrides));
    }
}
