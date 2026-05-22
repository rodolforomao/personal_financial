<?php

namespace Modules\Finance\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Application\Services\StatementImportService;

class StatementImportController extends Controller
{
    public function importOfx(Request $request, StatementImportService $import): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:ofx,xml,txt|max:10240',
        ]);

        $count = $import->importOfx(
            (int) $request->attributes->get('workspace_id'),
            $request->file('file')->getRealPath()
        );

        return response()->json([
            'imported' => $count,
            'message' => "{$count} lançamentos importados do OFX.",
        ]);
    }

    public function importCsv(Request $request, StatementImportService $import): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'mapping' => 'required|array',
            'mapping.amount' => 'required|string',
            'mapping.date' => 'required|string',
            'mapping.description' => 'nullable|string',
            'mapping.counterparty' => 'nullable|string',
        ]);

        $count = $import->importCsv(
            (int) $request->attributes->get('workspace_id'),
            $request->file('file')->getRealPath(),
            $request->input('mapping')
        );

        return response()->json([
            'imported' => $count,
            'message' => "{$count} lançamentos importados do CSV.",
        ]);
    }
}
