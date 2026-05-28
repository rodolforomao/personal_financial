<?php

namespace Modules\Projects\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Infrastructure\Models\Project;
use Modules\Projects\Infrastructure\Models\ProjectMilestone;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Project::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->with(['company', 'milestones'])
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

        $project->load('milestones');

        return response()->json([
            ...$project->toArray(),
            'roi' => round($project->roi(), 2),
            'profit' => $project->total_revenue - $project->total_cost,
            'progress_percent' => round($project->progressPercent(), 2),
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string',
            'budget' => 'sometimes|nullable|numeric',
            'expected_return' => 'sometimes|nullable|numeric',
            'total_cost' => 'sometimes|numeric',
            'total_revenue' => 'sometimes|numeric',
            'description' => 'sometimes|nullable|string',
            'starts_at' => 'sometimes|nullable|date',
            'ends_at' => 'sometimes|nullable|date',
            'milestones' => 'sometimes|array',
            'milestones.*.id' => 'nullable|integer',
            'milestones.*.name' => 'required_with:milestones|string|max:255',
            'milestones.*.weight_percent' => 'nullable|numeric|min:0|max:100',
            'milestones.*.is_completed' => 'nullable|boolean',
            'milestones.*.due_at' => 'nullable|date',
        ]);

        $milestones = $validated['milestones'] ?? null;
        unset($validated['milestones']);

        $project->update($validated);

        if (is_array($milestones)) {
            $this->syncMilestones($project, $milestones);
        }

        $project->load('milestones');

        return response()->json([
            ...$project->toArray(),
            'progress_percent' => round($project->progressPercent(), 2),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncMilestones(Project $project, array $rows): void
    {
        $rows = array_values(array_filter($rows, fn (array $row) => filled($row['name'] ?? null)));
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $payload = [
                'name' => $row['name'],
                'weight_percent' => $row['weight_percent'] ?? 0,
                'sort_order' => $index,
                'is_completed' => filter_var($row['is_completed'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'due_at' => $row['due_at'] ?? null,
            ];

            if (! empty($row['id'])) {
                $milestone = ProjectMilestone::query()
                    ->where('project_id', $project->id)
                    ->whereKey($row['id'])
                    ->first();

                if ($milestone) {
                    $milestone->update($payload);
                    $keptIds[] = $milestone->id;

                    continue;
                }
            }

            $created = $project->milestones()->create($payload);
            $keptIds[] = $created->id;
        }

        $project->milestones()->whereNotIn('id', $keptIds)->delete();
    }
}
