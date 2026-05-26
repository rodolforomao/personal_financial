<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Finance\Application\Services\StatementImportWorkflowService;
use Modules\Finance\Application\Services\StatementNettedPairService;
use Modules\Finance\Application\Services\StatementReconciliationService;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;

class StatementImportController extends Controller
{
    public function index(Request $request): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        return view('finance.statements.index', [
            'imports' => StatementImport::query()
                ->where('workspace_id', $workspaceId)
                ->with('user')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function store(Request $request, StatementImportWorkflowService $imports): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:ofx,csv,txt|max:20480',
            'format' => 'required|in:ofx,csv',
            'bank' => 'nullable|string|in:inter,nubank,itau,bradesco,santander,bb,caixa,c6,stone',
        ]);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $file = $request->file('file');
        $format = $request->input('format');
        $tmp = $file->store('tmp/statement-imports', 'local');
        $fullPath = Storage::disk('local')->path($tmp);

        try {
            if ($format === 'csv') {
                $headers = $imports->readCsvHeaders($fullPath);
                session([
                    'csv_import_path' => $tmp,
                    'csv_import_name' => $file->getClientOriginalName(),
                    'csv_headers' => $headers,
                ]);

                return redirect()->route('statements.import.csv-map');
            }

            $import = $imports->parseOfxForReview(
                $workspaceId,
                $request->user(),
                $fullPath,
                $file->getClientOriginalName(),
                $request->input('bank'),
            );
        } finally {
            if ($format === 'ofx') {
                Storage::disk('local')->delete($tmp);
            }
        }

        return redirect()
            ->route('statements.reconcile', $import)
            ->with('success', "Extrato importado: {$import->lines_total} lançamentos. Revise as sugestões de conciliação.");
    }

    public function csvMap(Request $request): View|RedirectResponse
    {
        if (! session('csv_import_path')) {
            return redirect()->route('statements.index')->with('error', 'Envie um CSV primeiro.');
        }

        return view('finance.statements.csv-map', [
            'headers' => session('csv_headers', []),
            'filename' => session('csv_import_name'),
        ]);
    }

    public function csvImport(Request $request, StatementImportWorkflowService $imports): RedirectResponse
    {
        $path = session('csv_import_path');
        if (! $path) {
            return redirect()->route('statements.index')->with('error', 'Sessão de importação expirada.');
        }

        $validated = $request->validate([
            'amount' => 'required|string',
            'date' => 'required|string',
            'description' => 'nullable|string',
            'counterparty' => 'nullable|string',
        ]);

        $mapping = [
            'amount' => $validated['amount'],
            'date' => $validated['date'],
        ];
        if (! empty($validated['description'])) {
            $mapping['description'] = $validated['description'];
        }
        if (! empty($validated['counterparty'])) {
            $mapping['counterparty'] = $validated['counterparty'];
        }

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $fullPath = Storage::disk('local')->path($path);

        $import = $imports->parseCsvForReview(
            $workspaceId,
            $request->user(),
            $fullPath,
            (string) session('csv_import_name', 'import.csv'),
            $mapping,
        );

        Storage::disk('local')->delete($path);
        $request->session()->forget(['csv_import_path', 'csv_import_name', 'csv_headers']);

        return redirect()
            ->route('statements.reconcile', $import)
            ->with('success', "CSV importado: {$import->lines_total} linhas.");
    }

    public function reconcile(
        Request $request,
        StatementImport $statementImport,
        StatementNettedPairService $nettedPairs,
    ): View {
        abort_unless(
            $statementImport->workspace_id === (int) $request->attributes->get('workspace_id'),
            404
        );

        $nettedPairs->markNettedPairs($statementImport);

        $statementImport->load([
            'lines' => fn ($q) => $q->visible()->with('transaction.category'),
        ]);

        return view('finance.statements.reconcile', [
            'import' => $statementImport->fresh(),
        ]);
    }

    public function confirmMatch(
        Request $request,
        StatementImport $statementImport,
        StatementLine $line,
        StatementReconciliationService $reconciliation,
    ): RedirectResponse {
        $this->authorizeLine($request, $statementImport, $line);
        $reconciliation->confirmMatch($line);

        return back()->with('success', 'Lançamento conciliado.');
    }

    public function importLine(
        Request $request,
        StatementImport $statementImport,
        StatementLine $line,
        StatementReconciliationService $reconciliation,
    ): RedirectResponse {
        $this->authorizeLine($request, $statementImport, $line);
        $tx = $reconciliation->importAsTransaction($line, $statementImport->workspace_id);

        return back()->with('success', "Transação #{$tx->id} criada e conciliada.");
    }

    public function skipLine(
        Request $request,
        StatementImport $statementImport,
        StatementLine $line,
        StatementReconciliationService $reconciliation,
    ): RedirectResponse {
        $this->authorizeLine($request, $statementImport, $line);
        $reconciliation->skipLine($line);

        return back()->with('success', 'Linha ignorada.');
    }

    public function confirmAllSuggested(
        Request $request,
        StatementImport $statementImport,
        StatementReconciliationService $reconciliation,
    ): RedirectResponse {
        abort_unless(
            $statementImport->workspace_id === (int) $request->attributes->get('workspace_id'),
            404
        );

        $count = $reconciliation->confirmAllSuggested($statementImport);

        return back()->with('success', "{$count} lançamento(s) conciliado(s).");
    }

    public function importAllUnmatched(
        Request $request,
        StatementImport $statementImport,
        StatementReconciliationService $reconciliation,
    ): RedirectResponse {
        abort_unless(
            $statementImport->workspace_id === (int) $request->attributes->get('workspace_id'),
            404
        );

        $count = $reconciliation->importAllUnmatched($statementImport);

        return back()->with('success', "{$count} transação(ões) criada(s) a partir do extrato.");
    }

    protected function authorizeLine(Request $request, StatementImport $import, StatementLine $line): void
    {
        abort_unless(
            $import->workspace_id === (int) $request->attributes->get('workspace_id')
            && $line->statement_import_id === $import->id,
            404
        );

        abort_if($line->match_status === StatementLine::STATUS_NETTED, 404);
    }
}
