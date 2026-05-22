<?php

namespace Modules\Projects\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Infrastructure\Models\Project;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Project::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->with('company')
                ->latest()
                ->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|integer',
            'budget' => 'nullable|numeric',
            'expected_return' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        $project = Project::query()->create([
            ...$validated,
            'workspace_id' => $request->attributes->get('workspace_id'),
        ]);

        return response()->json($project, 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            ...$project->toArray(),
            'roi' => round($project->roi(), 2),
            'profit' => $project->total_revenue - $project->total_cost,
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string',
            'budget' => 'sometimes|nullable|numeric',
            'total_cost' => 'sometimes|numeric',
            'total_revenue' => 'sometimes|numeric',
        ]));

        return response()->json($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }
}
