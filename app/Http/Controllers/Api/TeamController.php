<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Modules\Core\Infrastructure\Models\WorkspaceInvite;

class TeamController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        $members = User::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
            ->with(['roles', 'workspaces' => fn ($q) => $q->where('workspaces.id', $workspaceId)])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'workspace_role' => $user->workspaces->first()?->pivot?->role,
                'roles' => $user->roles->pluck('name'),
            ]);

        return response()->json(['data' => $members]);
    }

    public function invites(Request $request): JsonResponse
    {
        $this->authorize('invite', User::class);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        $invites = WorkspaceInvite::query()
            ->where('workspace_id', $workspaceId)
            ->pending()
            ->with('inviter:id,name,email')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $invites]);
    }

    public function invite(Request $request): JsonResponse
    {
        $this->authorize('invite', User::class);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => ['nullable', 'string', 'max:80', Rule::in(['owner', 'admin', 'member', 'viewer'])],
        ]);

        $email = strtolower($validated['email']);

        $alreadyMember = User::query()
            ->where('email', $email)
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
            ->exists();

        if ($alreadyMember) {
            return response()->json(['message' => 'Este e-mail já pertence ao workspace.'], 422);
        }

        WorkspaceInvite::query()
            ->where('workspace_id', $workspaceId)
            ->where('email', $email)
            ->pending()
            ->update(['revoked_at' => now()]);

        $invite = WorkspaceInvite::query()->create([
            'workspace_id' => $workspaceId,
            'email' => $email,
            'role' => $validated['role'] ?? 'member',
            'token' => WorkspaceInvite::generateToken(),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit->inviteSent($request->user(), $invite, [
            'workspace_id' => $workspaceId,
            'email' => $email,
            'role' => $invite->role,
        ]);

        return response()->json([
            'invite' => $invite->load('inviter:id,name,email'),
            'accept_url' => url('/api/v1/team/invite/accept'),
        ], 201);
    }

    public function revokeInvite(Request $request, WorkspaceInvite $invite): JsonResponse
    {
        $this->authorize('invite', User::class);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless($invite->workspace_id === $workspaceId, 404);
        abort_unless($invite->isPending(), 422, 'Convite não está pendente.');

        $invite->update(['revoked_at' => now()]);

        $this->audit->inviteRevoked($request->user(), $invite, [
            'workspace_id' => $workspaceId,
            'email' => $invite->email,
        ]);

        return response()->json(null, 204);
    }

    public function acceptInvite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|size:64',
            'name' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $invite = WorkspaceInvite::query()
            ->where('token', $validated['token'])
            ->first();

        if (! $invite || ! $invite->isPending()) {
            return response()->json(['message' => 'Convite inválido ou expirado.'], 404);
        }

        $user = User::query()->where('email', $invite->email)->first();

        if (! $user) {
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::query()->create([
                'name' => (string) $request->input('name'),
                'email' => $invite->email,
                'password' => Hash::make((string) $request->input('password')),
                'access_status' => User::ACCESS_PENDING_PAYMENT,
            ]);
        }

        $alreadyMember = $user->workspaces()
            ->where('workspaces.id', $invite->workspace_id)
            ->exists();

        if ($alreadyMember) {
            return response()->json(['message' => 'Usuário já pertence ao workspace.'], 422);
        }

        DB::transaction(function () use ($user, $invite) {
            $user->workspaces()->attach($invite->workspace_id, ['role' => $invite->role]);
            $invite->update(['accepted_at' => now()]);
        });

        $this->audit->inviteAccepted($user, $invite->fresh(), [
            'workspace_id' => $invite->workspace_id,
        ]);

        return response()->json([
            'message' => 'Convite aceito.',
            'workspace_id' => $invite->workspace_id,
            'user' => $user->load('workspaces'),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'workspace_role' => 'sometimes|string|max:80',
            'role' => 'sometimes|nullable|string|exists:roles,name',
        ]);

        if (array_key_exists('workspace_role', $validated)) {
            $user->workspaces()->updateExistingPivot($workspaceId, [
                'role' => $validated['workspace_role'],
            ]);
        }

        if (array_key_exists('role', $validated)) {
            if ($validated['role'] === null || $validated['role'] === '') {
                $user->syncRoles([]);
            } else {
                $user->syncRoles([$validated['role']]);
            }
        }

        return response()->json($user->fresh()->load('roles', 'workspaces'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $user->workspaces()->detach($workspaceId);

        return response()->json(null, 204);
    }
}
