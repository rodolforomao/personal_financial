<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Projects\Infrastructure\Models\Project;
use Modules\Projects\Infrastructure\Models\ProjectMilestone;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        return view('projects.index', [
            'projects' => Project::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->with(['company', 'milestones'])
                ->latest()
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('projects.create', [
            'companies' => $this->companiesForWorkspace($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|integer',
            'budget' => 'nullable|numeric',
            'expected_return' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        Project::query()->create([
            ...$validated,
            'workspace_id' => $request->attributes->get('workspace_id'),
        ]);

        return redirect()->route('projects.index')->with('success', 'Projeto criado.');
    }

    public function edit(Request $request, Project $project): View
    {
        $this->authorize('update', $project);

        $project->load('milestones');

        return view('projects.edit', [
            'project' => $project,
            'companies' => $this->companiesForWorkspace($request),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|integer',
            'status' => 'nullable|string|max:50',
            'budget' => 'nullable|numeric',
            'expected_return' => 'nullable|numeric',
            'total_cost' => 'nullable|numeric|min:0',
            'total_revenue' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'description' => 'nullable|string',
            'milestones' => 'nullable|array',
            'milestones.*.id' => 'nullable|integer',
            'milestones.*.name' => 'required_with:milestones|string|max:255',
            'milestones.*.weight_percent' => 'nullable|numeric|min:0|max:100',
            'milestones.*.is_completed' => 'nullable|boolean',
            'milestones.*.due_at' => 'nullable|date',
        ]);

        $milestones = $validated['milestones'] ?? [];
        unset($validated['milestones']);

        $project->update($validated);
        $this->syncMilestones($project, $milestones);

        return redirect()->route('projects.edit', $project)->with('success', 'Projeto atualizado.');
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

    protected function companiesForWorkspace(Request $request)
    {
        return Company::query()
            ->where('workspace_id', $request->attributes->get('workspace_id'))
            ->orderBy('name')
            ->get();
    }
}
