<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Categorization\Application\Services\BulkCategorizeTransactionsService;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Services\DataHygieneService;
use Modules\Finance\Application\Services\DuplicateTransactionService;
use Modules\Finance\Application\Services\TransactionBulkActionService;
use Modules\Operations\Infrastructure\Models\Operation;

class DataHygieneController extends Controller
{
    public function index(Request $request, DataHygieneService $hygiene, DuplicateTransactionService $duplicates): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $tab = $request->input('tab', 'overview');

        $woFilters = $request->only(['description', 'company_id', 'type', 'category_id', 'date_from', 'date_to']);
        $woFiltersActive = array_filter($woFilters) !== [];

        $duplicateGroups = $tab === 'duplicates' ? $duplicates->findGroups($workspaceId) : collect();

        return view('finance.data-hygiene.index', [
            'tab' => $tab,
            'audit' => $hygiene->audit($workspaceId, $duplicates),
            'duplicateGroups' => $duplicateGroups,
            'unitWarnings' => $hygiene->suspiciousOperationUnits($workspaceId),
            'withoutOperation' => $hygiene->withoutOperationQuery($workspaceId, $woFilters)
                ->latest('transaction_date')
                ->paginate(15, ['*'], 'without_op_page')
                ->withQueryString(),
            'woFilters' => $woFilters,
            'woFiltersActive' => $woFiltersActive,
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
            'companies' => Company::query()
                ->where('workspace_id', $workspaceId)
                ->orderBy('name')
                ->get(),
            'categories' => Category::query()
                ->where('workspace_id', $workspaceId)
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

    public function autoAssignOperations(Request $request, BulkCategorizeTransactionsService $service): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $result = $service->runOperationAssignment($workspaceId);

        if ($result['assigned'] === 0) {
            return redirect()
                ->route('data-hygiene.index', ['tab' => 'without_operation'])
                ->with('warning', 'Nenhuma transação teve operação vinculada automaticamente. Verifique se as operações estão cadastradas e com empresa associada.');
        }

        $msg = "{$result['assigned']} transação(ões) vinculada(s) à operação automaticamente.";
        $parts = [];
        if (! empty($result['via_company'])) {
            $parts[] = "{$result['via_company']} via empresa";
        }
        if (! empty($result['via_description'])) {
            $parts[] = "{$result['via_description']} via descrição";
        }
        if (! empty($result['via_rules'])) {
            $parts[] = "{$result['via_rules']} via regras de categorização";
        }
        if ($parts) {
            $msg .= ' (' . implode(', ', $parts) . ').';
        }

        return redirect()
            ->route('data-hygiene.index', ['tab' => 'without_operation'])
            ->with('success', $msg);
    }

    public function dismissDuplicate(Request $request, DuplicateTransactionService $duplicates): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $validated = $request->validate([
            'id1' => 'required|integer',
            'id2' => 'required|integer',
        ]);

        $duplicates->dismiss($workspaceId, (int) $validated['id1'], (int) $validated['id2']);

        return redirect()
            ->route('data-hygiene.index', ['tab' => 'duplicates'])
            ->with('success', 'Par marcado como não duplicado.');
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
