<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetWebWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $workspaceId = session('workspace_id')
                ?? $user->workspaces()->value('workspaces.id');

            if ($workspaceId) {
                session(['workspace_id' => $workspaceId]);
                $request->attributes->set('workspace_id', (int) $workspaceId);
                view()->share('currentWorkspace', $user->workspaces()->find($workspaceId));
            }
        }

        return $next($request);
    }
}
