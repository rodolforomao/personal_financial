<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    public function test_user_can_request_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'recover@example.com']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Strongpass123',
            'password_confirmation' => 'Strongpass123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Strongpass123', $user->fresh()->password));
    }
}
