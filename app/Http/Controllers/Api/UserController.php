<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        $query = User::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
            ->with('roles')
            ->orderBy('name');

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate((int) $request->integer('per_page', 20));

        return response()->json($users);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json($user->load('roles', 'workspaces'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:30',
            'role' => 'nullable|string|max:80',
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'access_status' => User::ACCESS_ACTIVE,
            'access_approved_at' => now(),
            'access_expires_at' => now()->addYear(),
        ]);

        $user->workspaces()->attach($workspaceId, ['role' => $validated['role'] ?? 'member']);

        return response()->json($user->load('roles', 'workspaces'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'password' => 'sometimes|string|min:8',
            'access_status' => ['sometimes', Rule::in([
                User::ACCESS_PENDING_PAYMENT,
                User::ACCESS_ACTIVE,
                User::ACCESS_MANUAL_RELEASE,
                User::ACCESS_BLOCKED,
            ])],
        ]);

        $user->update($validated);

        return response()->json($user->fresh()->load('roles', 'workspaces'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        $user->workspaces()->detach($workspaceId);

        if (! $user->workspaces()->exists()) {
            $user->delete();
        }

        return response()->json(null, 204);
    }
}
