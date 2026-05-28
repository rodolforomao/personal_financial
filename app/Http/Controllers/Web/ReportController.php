<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Application\Services\FinancialReportExportService;
use Modules\Finance\Application\Services\FinancialReportService;
use Modules\Finance\Application\Services\TransactionIndexFilterService;
use Modules\Operations\Infrastructure\Models\Operation;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(
        Request $request,
        FinancialReportService $reports,
        TransactionIndexFilterService $filters,
    ): View {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $operations = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->with(['activeUnits', 'company'])
            ->orderBy('name')
            ->get();

        $selectedOperationId = $request->integer('operation_id') ?: null;
        $operationUnits = $selectedOperationId
            ? $operations->firstWhere('id', $selectedOperationId)?->activeUnits ?? collect()
            : collect();

        return view('reports.index', [
            'report' => $reports->build($workspaceId, $request),
            'filtersActive' => $filters->hasActiveFilters($request),
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'companies' => Company::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'operations' => $operations,
            'operationUnits' => $operationUnits,
            'exportQuery' => $filters->queryParams($request),
        ]);
    }

    public function export(Request $request, FinancialReportExportService $export): Response
    {
        $format = (string) $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        return $format === 'pdf'
            ? $export->downloadPdf($workspaceId, $request)
            : $export->downloadXlsx($workspaceId, $request);
    }
}
