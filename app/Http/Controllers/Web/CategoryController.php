<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Infrastructure\Models\Transaction;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $month = now()->startOfMonth();

        $categories = Category::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) use ($workspaceId, $month) {
                $category->month_total = Transaction::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('category_id', $category->id)
                    ->where('type', 'expense')
                    ->where('transaction_date', '>=', $month)
                    ->sum('amount');

                return $category;
            });

        $uncategorized = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->whereNull('category_id')
            ->where('transaction_date', '>=', $month)
            ->sum('amount');

        return view('categories.index', [
            'categories' => $categories,
            'uncategorized' => $uncategorized,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:expense,income',
            'color' => 'nullable|string|max:20',
        ]);

        $slug = str()->slug($validated['name']);

        Category::query()->firstOrCreate(
            [
                'workspace_id' => $request->attributes->get('workspace_id'),
                'slug' => $slug,
            ],
            [
                'name' => $validated['name'],
                'type' => $validated['type'],
                'color' => $validated['color'] ?? '#6c757d',
            ]
        );

        return back()->with('success', 'Categoria criada.');
    }
}
