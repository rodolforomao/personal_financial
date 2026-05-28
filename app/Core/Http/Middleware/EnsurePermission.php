<?php

namespace App\Core\Http\Middleware;

use App\Support\SafePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * @param  string  ...$permissions  At least one permission must be granted (OR).
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if (SafePermission::check($user, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'Permissão insuficiente para esta ação.');
    }
}
