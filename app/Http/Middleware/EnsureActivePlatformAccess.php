<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivePlatformAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasActivePlatformAccess()) {
            return redirect()
                ->route('subscription.pending')
                ->with('warning', 'Seu cadastro ainda precisa de pagamento confirmado ou liberação do administrador.');
        }

        return $next($request);
    }
}
