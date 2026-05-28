<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Finance\Infrastructure\Models\Transaction;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportExportService
{
    /** @var list<string> */
    public const HEADERS = [
        'Nº',
        'Tipo',
        'Data',
        'Descrição',
        'Categoria',
        'Classificação',
        'Pagamento realizado por',
        'Valor (R$)',
    ];

    public function __construct(
        protected FinancialReportService $reports,
    ) {}

    /**
     * @return array{
     *     workspace_name: string,
     *     period_label: string,
     *     generated_at: string,
     *     totals: array<string, mixed>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function dataset(int $workspaceId, Request $request): array
    {
        $query = $this->reports->baseQuery($workspaceId, $request);
        $totals = $this->reports->totals($query);

        $transactions = (clone $query)
            ->with(['category', 'company', 'operation', 'operationUnit'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        return [
            'workspace_name' => Workspace::query()->whereKey($workspaceId)->value('name') ?? 'Workspace',
            'period_label' => $this->reports->periodLabel($request),
            'generated_at' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'totals' => $totals,
            'rows' => $this->mapRows($transactions),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapRows(Collection $transactions): array
    {
        $sequences = [
            TransactionType::Income->value => 0,
            TransactionType::Expense->value => 0,
            TransactionType::Transfer->value => 0,
        ];

        $rows = [];

        foreach ($transactions as $transaction) {
            /** @var Transaction $transaction */
            $type = $transaction->type;
            $typeKey = $type->value;
            $sequences[$typeKey]++;
            $sequence = $sequences[$typeKey];

            $rows[] = [
                'number' => sprintf('%s-%05d', $type->numberPrefix(), $sequence),
                'type_label' => $type->label(),
                'date' => $transaction->transaction_date->format('d/m/Y'),
                'date_sort' => $transaction->transaction_date->format('Y-m-d'),
                'description' => $transaction->description ?? '',
                'category' => $transaction->category?->name ?? '—',
                'classification' => $this->classificationLabel($transaction),
                'paid_by' => trim((string) $transaction->counterparty) !== ''
                    ? $transaction->counterparty
                    : '—',
                'amount' => (float) $transaction->amount,
                'amount_formatted' => number_format((float) $transaction->amount, 2, ',', '.'),
            ];
        }

        return $rows;
    }

    protected function classificationLabel(Transaction $transaction): string
    {
        $parts = [];

        if ($transaction->operation) {
            $label = $transaction->operation->name;
            if ($transaction->operationUnit) {
                $label .= ' · '.$transaction->operationUnit->displayName();
            }
            $parts[] = $label;
        }

        if ($transaction->company) {
            $parts[] = $transaction->company->name;
        }

        $funding = $transaction->fundingSourceLabel();
        $payment = $transaction->paymentMethodLabel();
        if ($funding || $payment) {
            $parts[] = trim(implode(' / ', array_filter([$funding, $payment])));
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    public function downloadXlsx(int $workspaceId, Request $request): StreamedResponse
    {
        $data = $this->dataset($workspaceId, $request);
        $spreadsheet = $this->buildSpreadsheet($data);
        $filename = $this->filename('xlsx');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadPdf(int $workspaceId, Request $request)
    {
        $data = $this->dataset($workspaceId, $request);

        return Pdf::loadView('reports.export-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($this->filename('pdf'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function buildSpreadsheet(array $data): Spreadsheet
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('Relatório');

        $active->setCellValue('A1', 'Relatório financeiro — '.$data['workspace_name']);
        $active->mergeCells('A1:H1');
        $active->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $active->setCellValue('A2', 'Período: '.$data['period_label']);
        $active->setCellValue('A3', 'Gerado em: '.$data['generated_at']);

        $totals = $data['totals'];
        $active->setCellValue('A4', sprintf(
            'Totais: Receitas R$ %s | Despesas R$ %s | Líquido R$ %s | %d lançamento(s)',
            number_format($totals['income'], 2, ',', '.'),
            number_format($totals['expense'], 2, ',', '.'),
            number_format($totals['net'], 2, ',', '.'),
            $totals['transaction_count'],
        ));
        $active->mergeCells('A4:H4');

        $headerRow = 6;
        foreach (self::HEADERS as $col => $header) {
            $coordinate = Coordinate::stringFromColumnIndex($col + 1).$headerRow;
            $active->setCellValue($coordinate, $header);
            $active->getStyle($coordinate)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '343A40'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $rowIndex = $headerRow + 1;
        foreach ($data['rows'] as $row) {
            $active->fromArray([
                $row['number'],
                $row['type_label'],
                $row['date'],
                $row['description'],
                $row['category'],
                $row['classification'],
                $row['paid_by'],
                $row['amount'],
            ], null, 'A'.$rowIndex);
            $active->getStyle('H'.$rowIndex)
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $rowIndex++;
        }

        foreach (range('A', 'H') as $column) {
            $active->getColumnDimension($column)->setAutoSize(true);
        }

        $active->freezePane('A'.($headerRow + 1));

        return $sheet;
    }

    protected function filename(string $extension): string
    {
        return 'relatorio-financeiro-'.now()->format('Y-m-d-His').'.'.$extension;
    }
}
