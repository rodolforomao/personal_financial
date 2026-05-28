<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspaceId = $request->header('X-Workspace-Id')
            ?? $request->route('workspace')
            ?? ($request->user()?->workspaces()->value('workspaces.id'));

        if ($workspaceId && $request->user()) {
            $hasAccess = $request->user()
                ->workspaces()
                ->where('workspaces.id', $workspaceId)
                ->exists();

            abort_unless($hasAccess, 403, 'Workspace access denied.');
            $request->attributes->set('workspace_id', (int) $workspaceId);
        }

        return $next($request);
    }
}
