<?php

namespace Modules\Categorization\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Categorization\Infrastructure\Models\Category;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where('workspace_id', $request->attributes->get('workspace_id'))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:expense,income',
            'parent_id' => 'nullable|integer',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
        ]);

        $category = Category::query()->create([
            ...$validated,
            'workspace_id' => $request->attributes->get('workspace_id'),
            'slug' => str()->slug($validated['name']),
        ]);

        return response()->json($category, 201);
    }
}
