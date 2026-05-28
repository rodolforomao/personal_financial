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
    public const VIEW_SUMMARY = 'resumo';

    public const VIEW_DETAIL = 'detalhado';

    public function index(
        Request $request,
        FinancialReportService $reports,
        FinancialReportExportService $export,
        TransactionIndexFilterService $filters,
    ): View {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $view = $this->resolveView($request, $filters);
        $operations = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->with(['activeUnits', 'company'])
            ->orderBy('name')
            ->get();

        $selectedOperationId = $request->integer('operation_id') ?: null;
        $operationUnits = $selectedOperationId
            ? $operations->firstWhere('id', $selectedOperationId)?->activeUnits ?? collect()
            : collect();

        $exportData = $export->dataset($workspaceId, $request);
        $queryParams = array_merge($filters->queryParams($request), ['view' => $view]);

        return view('reports.index', [
            'view' => $view,
            'report' => $reports->build($workspaceId, $request),
            'detailRows' => $exportData['rows'],
            'filtersActive' => $filters->hasActiveFilters($request),
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'companies' => Company::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'operations' => $operations,
            'operationUnits' => $operationUnits,
            'exportQuery' => $filters->queryParams($request),
            'summaryUrl' => route('reports.index', array_merge($queryParams, ['view' => self::VIEW_SUMMARY])),
            'detailUrl' => route('reports.index', array_merge($queryParams, ['view' => self::VIEW_DETAIL])),
        ]);
    }

    protected function resolveView(Request $request, TransactionIndexFilterService $filters): string
    {
        if ($request->has('view')) {
            $view = (string) $request->query('view');

            return in_array($view, [self::VIEW_SUMMARY, self::VIEW_DETAIL], true)
                ? $view
                : self::VIEW_SUMMARY;
        }

        return $filters->hasActiveFilters($request)
            ? self::VIEW_DETAIL
            : self::VIEW_SUMMARY;
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
