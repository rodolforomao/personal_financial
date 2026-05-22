<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Infrastructure\Models\RecurringItem;

class RecurringItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = RecurringItem::query()
            ->where('workspace_id', $request->attributes->get('workspace_id'))
            ->with(['category', 'company'])
            ->orderBy('next_due_at')
            ->get();

        return response()->json($items);
    }
}
