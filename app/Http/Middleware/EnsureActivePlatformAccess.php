<?php

namespace App\Http\Middleware;

use App\Services\UserAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivePlatformAccess
{
    public function __construct(private readonly UserAccessService $accessService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $user = $this->accessService->syncPaymentAccess($user);
            $request->setUserResolver(fn () => $user);
        }

        if (! $user?->hasActivePlatformAccess()) {
            return redirect()
                ->route('subscription.pending')
                ->with('warning', 'Seu cadastro ainda precisa de pagamento em dia ou liberação do administrador.');
        }

        return $next($request);
    }
}
