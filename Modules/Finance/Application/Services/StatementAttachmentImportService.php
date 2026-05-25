<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;

class StatementAttachmentImportService
{
    public function __construct(
        protected StatementImportService $imports,
        protected StatementReconciliationService $reconciliation,
    ) {}

    /**
     * @return array{
     *     handled: bool,
     *     import?: StatementImport,
     *     imported_count?: int,
     *     suggested_count?: int,
     *     review_url?: string,
     *     bulk_url?: string,
     *     error?: string,
     *     headers?: list<string>
     * }
     */
    public function importAttachment(
        int $workspaceId,
        User $user,
        string $filePath,
        ?string $originalName = null,
        ?string $mime = null,
    ): array {
        $format = $this->detectFormat($filePath, $originalName, $mime);
        if ($format === null) {
            return ['handled' => false];
        }

        if ($format === 'csv') {
            $mapping = $this->guessCsvMapping($filePath);
            if ($mapping === null) {
                return [
                    'handled' => true,
                    'error' => 'csv_mapping_required',
                    'headers' => $this->imports->readCsvHeaders($filePath),
                ];
            }

            $import = $this->imports->parseCsv(
                $workspaceId,
                $user,
                $filePath,
                $this->originalName($filePath, $originalName),
                $mapping,
            );
        } else {
            $import = $this->imports->parseOfx(
                $workspaceId,
                $user,
                $filePath,
                $this->originalName($filePath, $originalName),
            );
        }

        $suggested = $import->lines()->where('match_status', StatementLine::STATUS_SUGGESTED)->count();
        $created = $this->reconciliation->importAllUnmatched($import);
        $import = $import->fresh();

        return [
            'handled' => true,
            'import' => $import,
            'imported_count' => $created,
            'suggested_count' => $suggested,
            'review_url' => route('statements.reconcile', $import),
            'bulk_url' => route('transactions.index', [
                'statement_import_id' => $import->id,
                'per_page' => 100,
            ]),
        ];
    }

    protected function detectFormat(string $filePath, ?string $originalName, ?string $mime): ?string
    {
        $name = Str::lower((string) $originalName);
        $mime = Str::lower((string) $mime);
        $sample = Str::lower((string) @file_get_contents($filePath, false, null, 0, 4096));

        if (
            str_ends_with($name, '.ofx')
            || str_ends_with($name, '.qfx')
            || str_contains($mime, 'ofx')
            || str_contains($sample, '<ofx')
            || str_contains($sample, '<stmttrn>')
        ) {
            return 'ofx';
        }

        if (
            str_ends_with($name, '.csv')
            || str_contains($mime, 'csv')
            || str_contains($mime, 'comma-separated-values')
        ) {
            return 'csv';
        }

        return null;
    }

    /**
     * @return array{amount: string, date: string, description?: string, counterparty?: string}|null
     */
    protected function guessCsvMapping(string $filePath): ?array
    {
        $headers = $this->imports->readCsvHeaders($filePath);
        $amount = $this->findHeader($headers, ['valor', 'amount', 'vlr', 'value']);
        $date = $this->findHeader($headers, ['data', 'date', 'dt']);

        if ($amount === null || $date === null) {
            return null;
        }

        $mapping = [
            'amount' => $amount,
            'date' => $date,
        ];

        $description = $this->findHeader($headers, [
            'descricao',
            'descrição',
            'description',
            'historico',
            'histórico',
            'memo',
            'detalhe',
            'lançamento',
            'lancamento',
        ]);
        if ($description !== null) {
            $mapping['description'] = $description;
        }

        $counterparty = $this->findHeader($headers, [
            'contraparte',
            'favorecido',
            'beneficiario',
            'beneficiário',
            'merchant',
            'estabelecimento',
            'nome',
            'name',
        ]);
        if ($counterparty !== null) {
            $mapping['counterparty'] = $counterparty;
        }

        return $mapping;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $needles
     */
    protected function findHeader(array $headers, array $needles): ?string
    {
        foreach ($headers as $header) {
            $normalized = Str::of($header)->ascii()->lower()->value();
            foreach ($needles as $needle) {
                if (str_contains($normalized, Str::ascii(Str::lower($needle)))) {
                    return $header;
                }
            }
        }

        return null;
    }

    protected function originalName(string $filePath, ?string $originalName): string
    {
        $originalName = trim((string) $originalName);

        return $originalName !== '' ? $originalName : basename($filePath);
    }
}
