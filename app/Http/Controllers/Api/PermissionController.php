<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return response()->json(['data' => $permissions]);
    }
}
