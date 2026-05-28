<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $actor = $request->user();

        $canManageAll = $actor->can('tokens.manage');

        $userIds = $canManageAll
            ? User::query()
                ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
                ->pluck('id')
            : collect([$actor->id]);

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'user_id' => $token->tokenable_id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'is_current' => $actor->currentAccessToken()
                    && (int) $actor->currentAccessToken()->id === (int) $token->id,
            ]);

        return response()->json(['data' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'sometimes|array',
            'abilities.*' => 'string',
            'user_id' => 'sometimes|integer|exists:users,id',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        $target = $actor;

        if (! empty($validated['user_id']) && (int) $validated['user_id'] !== $actor->id) {
            abort_unless($actor->can('tokens.manage'), 403);
            $target = User::query()->findOrFail($validated['user_id']);
            $this->ensureSameWorkspace($request, $target);
        }

        $expiresAt = isset($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : null;

        $token = $target->createToken(
            $validated['name'],
            $validated['abilities'] ?? ['*'],
            $expiresAt,
        );

        $this->audit->tokenCreated($actor, $token->accessToken, [
            'workspace_id' => $request->attributes->get('workspace_id'),
            'token_id' => $token->accessToken->id,
            'target_user_id' => $target->id,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'access_token' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
            ],
        ], 201);
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $accessToken = PersonalAccessToken::query()->findOrFail($token);
        $actor = $request->user();

        if ((int) $accessToken->tokenable_id !== $actor->id) {
            abort_unless($actor->can('tokens.manage'), 403);
            $target = User::query()->findOrFail($accessToken->tokenable_id);
            $this->ensureSameWorkspace($request, $target);
        }

        $this->audit->tokenRevoked($actor, $accessToken, [
            'workspace_id' => $request->attributes->get('workspace_id'),
            'token_id' => $accessToken->id,
        ]);

        $accessToken->delete();

        return response()->json(null, 204);
    }

    private function ensureSameWorkspace(Request $request, User $user): void
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        abort_unless(
            $user->workspaces()->where('workspaces.id', $workspaceId)->exists(),
            404,
        );
    }
}
