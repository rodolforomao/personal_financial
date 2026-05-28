<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Infrastructure\Models\Workspace;

class AuthController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLogger $audit,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'access_status' => User::ACCESS_PENDING_PAYMENT,
        ]);

        $workspace = Workspace::query()->create([
            'name' => 'Workspace de '.$user->name,
            'slug' => 'usuario-'.$user->id,
            'currency' => 'BRL',
        ]);
        $workspace->users()->attach($user->id, ['role' => 'owner']);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('workspaces'),
            'workspace_id' => $workspace->id,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $token = $user->createToken($validated['device_name'] ?? 'api')->plainTextToken;

        $this->audit->login($user, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'workspace_id' => $request->header('X-Workspace-Id'),
            'channel' => 'api',
        ]);

        return response()->json([
            'token' => $token,
            'user' => $user->load('workspaces'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('workspaces'));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceId = $request->attributes->get('workspace_id') ?? $request->header('X-Workspace-Id');

        $user->currentAccessToken()?->delete();

        $this->audit->logout($user, [
            'ip' => $request->ip(),
            'workspace_id' => $workspaceId,
            'channel' => 'api',
        ]);

        return response()->json(['message' => 'Logout efetuado.']);
    }

    public function switchWorkspace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workspace_id' => 'required|integer',
        ]);

        $workspaceId = (int) $validated['workspace_id'];
        $hasAccess = $request->user()
            ->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->exists();

        abort_unless($hasAccess, 403, 'Workspace access denied.');

        return response()->json([
            'workspace_id' => $workspaceId,
            'message' => 'Workspace ativo atualizado para próximas requisições via X-Workspace-Id.',
        ]);
    }
}
