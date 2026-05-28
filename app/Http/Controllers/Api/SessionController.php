<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $canManageOthers = $request->user()->can('sessions.revoke');

        $userIds = $canManageOthers
            ? User::query()
                ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
                ->pluck('id')
            : collect([$request->user()->id]);

        $sessions = DB::table('sessions')
            ->whereIn('user_id', $userIds)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => $session->last_activity,
                'last_activity_at' => date('c', (int) $session->last_activity),
            ]);

        return response()->json(['data' => $sessions]);
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        $row = DB::table('sessions')->where('id', $session)->first();

        if (! $row) {
            return response()->json(['message' => 'Sessão não encontrada.'], 404);
        }

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $targetUserId = (int) $row->user_id;
        $isOwnSession = $targetUserId === $request->user()->id;

        if ($isOwnSession) {
            abort_unless(
                $request->user()->can('sessions.view') || $request->user()->can('sessions.revoke'),
                403,
                'Permissão negada.',
            );
        } else {
            abort_unless($request->user()->can('sessions.revoke'), 403, 'Permissão negada.');
            $inWorkspace = User::query()
                ->where('id', $targetUserId)
                ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
                ->exists();
            abort_unless($inWorkspace, 403, 'Sessão fora do workspace.');
        }

        DB::table('sessions')->where('id', $session)->delete();

        $this->audit->sessionRevoked($request->user(), [
            'session_id' => $session,
            'target_user_id' => $targetUserId,
            'workspace_id' => $workspaceId,
            'own_session' => $isOwnSession,
        ]);

        return response()->json(null, 204);
    }
}
