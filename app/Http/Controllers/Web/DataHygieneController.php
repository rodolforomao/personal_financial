<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Finance\Application\Services\DataHygieneService;
use Modules\Finance\Application\Services\TransactionBulkActionService;
use Modules\Operations\Infrastructure\Models\Operation;

class DataHygieneController extends Controller
{
    public function index(Request $request, DataHygieneService $hygiene): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $tab = $request->input('tab', 'overview');

        return view('finance.data-hygiene.index', [
            'tab' => $tab,
            'audit' => $hygiene->audit($workspaceId),
            'unitWarnings' => $hygiene->suspiciousOperationUnits($workspaceId),
            'withoutOperation' => $hygiene->withoutOperationQuery($workspaceId)
                ->latest('transaction_date')
                ->paginate(15, ['*'], 'without_op_page')
                ->withQueryString(),
            'missingUnit' => $hygiene->missingUnitQuery($workspaceId)
                ->with(['category', 'operation', 'company'])
                ->latest('transaction_date')
                ->paginate(15, ['*'], 'missing_unit_page')
                ->withQueryString(),
            'operations' => Operation::query()
                ->where('workspace_id', $workspaceId)
                ->with(['activeUnits', 'company'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function applyFix(Request $request, DataHygieneService $hygiene): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'action' => 'required|in:fix_company_types,show_geral_on_dashboard,release_geral_transactions',
        ]);

        $message = match ($validated['action']) {
            'fix_company_types' => $this->messageCompanyTypes($hygiene->fixKnownCompanyTypes($workspaceId)),
            'show_geral_on_dashboard' => $hygiene->showGeralOnConsolidatedDashboard($workspaceId)
                ? 'Operação Geral agora aparece no dashboard consolidado (filtro “todas” desligado).'
                : 'Operação “Geral” não encontrada — nada alterado.',
            'release_geral_transactions' => $this->messageReleaseGeral(
                $hygiene->releaseGeralTransactionsToPersonal($workspaceId),
            ),
        };

        return redirect()
            ->route('data-hygiene.index')
            ->with('success', $message);
    }

    public function bulkAssign(Request $request, TransactionBulkActionService $bulk): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'operation_id' => 'nullable|integer',
            'operation_unit_id' => 'nullable|integer',
            'tab' => 'nullable|string',
        ]);

        $changes = [];
        if (! empty($validated['operation_unit_id'])) {
            $changes['operation_unit_id'] = (int) $validated['operation_unit_id'];
        } elseif (! empty($validated['operation_id'])) {
            $changes['operation_id'] = (int) $validated['operation_id'];
        } else {
            return back()->with('warning', 'Selecione uma operação ou unidade.');
        }

        $result = $bulk->apply($workspaceId, $validated['ids'], $changes, $request->user());

        $tab = $validated['tab'] ?? 'missing_unit';

        return redirect()
            ->route('data-hygiene.index', ['tab' => $tab])
            ->with('success', "{$result['updated']} lançamento(s) atualizado(s).");
    }

    protected function messageCompanyTypes(int $fixed): string
    {
        if ($fixed === 0) {
            return 'Tipos de empresa já estavam corretos (ou nomes não encontrados).';
        }

        return "{$fixed} empresa(s) atualizada(s) para “Minha empresa”.";
    }

    protected function messageReleaseGeral(int $count): string
    {
        if ($count === 0) {
            return 'Nenhuma transação na operação Geral para liberar.';
        }

        return "{$count} lançamento(s) movidos para o escopo pessoal (sem operação).";
    }
}
