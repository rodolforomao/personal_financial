<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionProfile;
use App\Models\User;
use App\Services\LiquidxPaymentService;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Modules\Core\Infrastructure\Models\Workspace;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $profile = $this->defaultSubscriptionProfile();

        return view('auth.register', compact('profile'));
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['email' => __($status)]);
        }

        return back()->with('success', 'Enviamos o link para redefinir sua senha.');
    }

    public function showResetPassword(string $token, Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Senha redefinida com sucesso.');
    }

    public function login(Request $request, UserAccessService $accessService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $workspace = $request->user()->workspaces()->first();
        if ($workspace) {
            session(['workspace_id' => $workspace->id]);
        }

        $user = $accessService->syncPaymentAccess($request->user());

        if (! $user->hasActivePlatformAccess()) {
            return redirect()
                ->route('subscription.pending')
                ->with('warning', 'Seu cadastro precisa de pagamento em dia ou aprovação do administrador.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $profile = $this->defaultSubscriptionProfile();

        $user = DB::transaction(function () use ($validated, $profile): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'subscription_profile_id' => $profile->id,
                'access_status' => User::ACCESS_PENDING_PAYMENT,
            ]);

            $workspace = Workspace::query()->create([
                'name' => 'Workspace de '.$user->name,
                'slug' => 'usuario-'.$user->id,
                'currency' => 'BRL',
            ]);

            $workspace->users()->attach($user->id, ['role' => 'owner']);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        session(['workspace_id' => $user->workspaces()->value('workspaces.id')]);

        return redirect()
            ->route('subscription.pending')
            ->with('success', 'Cadastro criado. Confirme o pagamento para liberar o acesso automaticamente.');
    }

    public function pendingSubscription(Request $request, LiquidxPaymentService $payments, UserAccessService $accessService): View|RedirectResponse
    {
        $user = $accessService->syncPaymentAccess($request->user())->loadMissing('subscriptionProfile');

        if ($user->hasActivePlatformAccess()) {
            return redirect()->route('dashboard');
        }

        return view('auth.subscription-pending', [
            'user' => $user,
            'profile' => $user->subscriptionProfile ?? $this->defaultSubscriptionProfile(),
            'payment' => $payments->latestPaymentFor($user),
            'liquidxConfigured' => $payments->configured(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchWorkspace(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id' => 'required|integer',
        ]);

        $workspaceId = (int) $validated['workspace_id'];
        $allowed = $request->user()
            ->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->exists();

        abort_unless($allowed, 403, 'Workspace access denied.');

        session(['workspace_id' => $workspaceId]);

        $workspace = Workspace::query()->find($workspaceId);
        $label = $workspace?->name ?? "#{$workspaceId}";

        $redirect = $request->input('redirect_to');
        if (is_string($redirect) && str_starts_with($redirect, url('/'))) {
            return redirect($redirect)->with('success', "Workspace alterado para {$label}.");
        }

        return redirect()
            ->route('dashboard')
            ->with('success', "Workspace alterado para {$label}.");
    }

    private function defaultSubscriptionProfile(): SubscriptionProfile
    {
        return SubscriptionProfile::query()->firstOrCreate(
            ['slug' => 'mensal'],
            [
                'name' => 'Mensal',
                'monthly_price_cents' => 2000,
                'description' => 'Acesso padrão ao Financial IQ.',
                'is_active' => true,
            ]
        );
    }
}
