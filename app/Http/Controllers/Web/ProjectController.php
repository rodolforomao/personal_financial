<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Projects\Infrastructure\Models\Project;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        return view('projects.index', [
            'projects' => Project::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->with('company')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        return view('projects.create', [
            'companies' => Company::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->orderBy('name')
                ->get(),
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
}
