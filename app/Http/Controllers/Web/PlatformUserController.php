<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionProfile;
use App\Models\User;
use App\Models\UserAccessPayment;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['subscriptionProfile', 'accessApprovedBy', 'latestAccessPayment'])
            ->orderBy('name')
            ->paginate(25);

        $profiles = SubscriptionProfile::query()
            ->orderByDesc('is_active')
            ->orderBy('monthly_price_cents')
            ->get();

        $stats = [
            'active' => User::query()->whereIn('access_status', [User::ACCESS_ACTIVE, User::ACCESS_MANUAL_RELEASE])->count(),
            'pending' => User::query()->where('access_status', User::ACCESS_PENDING_PAYMENT)->count(),
            'blocked' => User::query()->where('access_status', User::ACCESS_BLOCKED)->count(),
        ];

        return view('admin.users.index', compact('users', 'profiles', 'stats'));
    }

    public function toggleInternal(Request $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'is_platform_internal' => ! $user->is_platform_internal,
        ])->save();

        $label = $user->is_platform_internal ? 'ativado como interno' : 'desativado como interno';

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Usuário {$user->name} {$label}. IA da plataforma sem cobrança.");
    }

    public function updateAccess(Request $request, User $user, UserAccessService $accessService): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_profile_id' => ['nullable', Rule::exists('subscription_profiles', 'id')],
            'access_status' => ['required', Rule::in([
                User::ACCESS_PENDING_PAYMENT,
                User::ACCESS_ACTIVE,
                User::ACCESS_MANUAL_RELEASE,
                User::ACCESS_BLOCKED,
            ])],
            'access_expires_at' => 'nullable|date',
            'is_platform_internal' => 'nullable|boolean',
        ]);

        if ($request->user()->is($user) && $validated['access_status'] === User::ACCESS_BLOCKED) {
            return back()->with('error', 'Você não pode bloquear o próprio usuário administrador.');
        }

        $expiresAt = isset($validated['access_expires_at'])
            ? Carbon::parse($validated['access_expires_at'])->endOfDay()
            : null;

        $user->forceFill([
            'subscription_profile_id' => $validated['subscription_profile_id'] ?? null,
            'is_platform_internal' => $request->boolean('is_platform_internal'),
        ])->save();

        match ($validated['access_status']) {
            User::ACCESS_MANUAL_RELEASE => $accessService->grantManualAccess($user, $request->user(), $expiresAt),
            User::ACCESS_BLOCKED => $accessService->blockAccess($user),
            default => $user->forceFill([
                'access_status' => $validated['access_status'],
                'access_approved_at' => $validated['access_status'] === User::ACCESS_ACTIVE ? now() : null,
                'access_approved_by' => $validated['access_status'] === User::ACCESS_ACTIVE ? $request->user()->id : null,
                'access_expires_at' => $expiresAt,
            ])->save(),
        };

        if (! $user->fresh()->hasActivePlatformAccess()) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Acesso de {$user->name} atualizado.");
    }

    public function confirmPayment(Request $request, User $user, UserAccessService $accessService): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_profile_id' => ['required', Rule::exists('subscription_profiles', 'id')],
            'paid_at' => 'nullable|date',
            'months' => 'required|integer|min:1|max:24',
        ]);

        $profile = SubscriptionProfile::query()->findOrFail($validated['subscription_profile_id']);
        $paidAt = isset($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : now();
        $periodStart = $user->access_expires_at?->isFuture() ? $user->access_expires_at->copy() : $paidAt->copy();
        $periodEnd = $periodStart->copy()->addMonthsNoOverflow((int) $validated['months']);

        $payment = UserAccessPayment::query()->create([
            'user_id' => $user->id,
            'subscription_profile_id' => $profile->id,
            'amount_cents' => $profile->monthly_price_cents * (int) $validated['months'],
            'currency' => 'BRL',
            'status' => 'pending',
            'provider' => 'manual_admin',
            'billing_period_starts_at' => $periodStart,
            'billing_period_ends_at' => $periodEnd,
            'metadata' => [
                'confirmed_by' => $request->user()->id,
                'source' => 'admin_users_screen',
            ],
        ]);

        $accessService->markPaymentAsPaid($payment, $paidAt);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Pagamento de {$profile->monthlyPriceLabel()} confirmado para {$user->name}. Acesso liberado até {$periodEnd->format('d/m/Y')}.");
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $validated = $this->validateProfile($request);

        SubscriptionProfile::query()->create([
            'name' => $validated['name'],
            'slug' => $this->uniqueProfileSlug($validated['name']),
            'monthly_price_cents' => $this->priceToCents($validated['monthly_price']),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Perfil de usuário criado.');
    }

    public function updateProfile(Request $request, SubscriptionProfile $subscriptionProfile): RedirectResponse
    {
        $validated = $this->validateProfile($request);

        $subscriptionProfile->update([
            'name' => $validated['name'],
            'monthly_price_cents' => $this->priceToCents($validated['monthly_price']),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Perfil de usuário atualizado.');
    }

    /**
     * @return array{name: string, monthly_price: numeric, description?: ?string}
     */
    private function validateProfile(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'monthly_price' => 'required|numeric|min:0|max:999999',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function priceToCents(float|int|string $price): int
    {
        return (int) round(((float) str_replace(',', '.', (string) $price)) * 100);
    }

    private function uniqueProfileSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'perfil';
        $slug = $base;
        $suffix = 2;

        while (SubscriptionProfile::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
