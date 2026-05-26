<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Application\Services\StatementImportWorkflowService;

class StatementImportController extends Controller
{
    public function importOfx(Request $request, StatementImportWorkflowService $imports): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:ofx,xml,txt|max:10240',
        ]);

        $result = $imports->importOfxAndCreateTransactions(
            (int) $request->attributes->get('workspace_id'),
            $request->user(),
            $request->file('file')->getRealPath(),
            $request->file('file')->getClientOriginalName()
        );
        $count = $result['imported_count'];

        return response()->json([
            'imported' => $count,
            'message' => "{$count} lançamentos importados do OFX.",
        ]);
    }

    public function importCsv(Request $request, StatementImportWorkflowService $imports): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'mapping' => 'required|array',
            'mapping.amount' => 'required|string',
            'mapping.date' => 'required|string',
            'mapping.description' => 'nullable|string',
            'mapping.counterparty' => 'nullable|string',
        ]);

        $result = $imports->importCsvAndCreateTransactions(
            (int) $request->attributes->get('workspace_id'),
            $request->user(),
            $request->file('file')->getRealPath(),
            $request->file('file')->getClientOriginalName(),
            $request->input('mapping')
        );
        $count = $result['imported_count'];

        return response()->json([
            'imported' => $count,
            'message' => "{$count} lançamentos importados do CSV.",
        ]);
    }
}
