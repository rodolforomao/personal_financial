<?php

namespace App\Http\Middleware;

use App\Support\SafePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isAllowed = $user?->hasAnyRole(['SUPER_ADMIN', 'TENANT_OWNER', 'ADMIN', 'admin'])
            || SafePermission::check($user, 'settings.manage');

        if (! $isAllowed) {
            abort(403, 'Acesso restrito a administradores da plataforma.');
        }

        return $next($request);
    }
}
