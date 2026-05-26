<?php

namespace Modules\Finance\Application\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;

class StatementImportWorkflowService
{
    public function __construct(
        protected StatementImportService $imports,
        protected StatementReconciliationService $reconciliation,
    ) {}

    public function parseOfxForReview(
        int $workspaceId,
        ?User $user,
        string $filePath,
        string $originalName,
        ?string $bankSlug = null,
    ): StatementImport {
        return $this->imports->parseOfx($workspaceId, $user, $filePath, $originalName, $bankSlug);
    }

    /**
     * @param  array{amount: string, date: string, description?: string, counterparty?: string}  $mapping
     */
    public function parseCsvForReview(
        int $workspaceId,
        ?User $user,
        string $filePath,
        string $originalName,
        array $mapping,
    ): StatementImport {
        return $this->imports->parseCsv($workspaceId, $user, $filePath, $originalName, $mapping);
    }

    /**
     * @return array{import: StatementImport, imported_count: int}
     */
    public function importOfxAndCreateTransactions(
        int $workspaceId,
        ?User $user,
        string $filePath,
        string $originalName,
        ?string $bankSlug = null,
    ): array {
        $import = $this->parseOfxForReview($workspaceId, $user, $filePath, $originalName, $bankSlug);
        $count = $this->reconciliation->importAllUnmatched($import);

        return [
            'import' => $import->fresh(),
            'imported_count' => $count,
        ];
    }

    /**
     * @param  array{amount: string, date: string, description?: string, counterparty?: string}  $mapping
     * @return array{import: StatementImport, imported_count: int}
     */
    public function importCsvAndCreateTransactions(
        int $workspaceId,
        ?User $user,
        string $filePath,
        string $originalName,
        array $mapping,
    ): array {
        $import = $this->parseCsvForReview($workspaceId, $user, $filePath, $originalName, $mapping);
        $count = $this->reconciliation->importAllUnmatched($import);

        return [
            'import' => $import->fresh(),
            'imported_count' => $count,
        ];
    }

    /**
     * @return list<string>
     */
    public function readCsvHeaders(string $filePath): array
    {
        return $this->imports->readCsvHeaders($filePath);
    }

    /**
     * Used by Telegram/WhatsApp after the channel has downloaded the media.
     *
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
    public function importAttachmentAndCreateTransactions(
        int $workspaceId,
        User $user,
        string $filePath,
        ?string $originalName = null,
        ?string $mime = null,
    ): array {
        $format = $this->detectAttachmentFormat($filePath, $originalName, $mime);
        if ($format === null) {
            return ['handled' => false];
        }

        if ($format === 'csv') {
            $mapping = $this->guessCsvMapping($filePath);
            if ($mapping === null) {
                return [
                    'handled' => true,
                    'error' => 'csv_mapping_required',
                    'headers' => $this->readCsvHeaders($filePath),
                ];
            }

            $import = $this->parseCsvForReview(
                $workspaceId,
                $user,
                $filePath,
                $this->originalName($filePath, $originalName),
                $mapping,
            );
        } else {
            $import = $this->parseOfxForReview(
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
            ...$this->reviewUrls($import),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function channelReply(array $result, string $channel): string
    {
        if (($result['error'] ?? null) === 'csv_mapping_required') {
            $headers = implode(', ', array_slice($result['headers'] ?? [], 0, 8));

            return "Recebi o CSV, mas não consegui identificar automaticamente as colunas de data e valor.\n".
                "Colunas encontradas: {$headers}\n".
                'Importe esse CSV pela tela de extratos para mapear as colunas: '.route('statements.index');
        }

        $import = $result['import'] ?? null;
        $total = $import?->lines_total ?? 0;
        $created = (int) ($result['imported_count'] ?? 0);
        $suggested = (int) ($result['suggested_count'] ?? 0);
        $netted = $import?->netted_count ?? 0;

        $parts = [
            "✅ Extrato importado pelo {$channel}.\n".
            "Linhas: {$total} | Transações criadas: {$created}",
        ];

        if ($suggested > 0) {
            $parts[] = "Sugestões de conciliação para revisar: {$suggested}";
        }

        if ($netted > 0) {
            $parts[] = "Estornos ocultados: {$netted}";
        }

        $parts[] = 'Revisar conciliação: '.$result['review_url'];
        if ($created > 0) {
            $parts[] = 'Editar importadas em massa: '.$result['bulk_url'];
        }

        return implode("\n", $parts);
    }

    /**
     * @return array{review_url: string, bulk_url: string}
     */
    public function reviewUrls(StatementImport $import): array
    {
        return [
            'review_url' => route('statements.reconcile', $import),
            'bulk_url' => route('transactions.index', [
                'statement_import_id' => $import->id,
                'per_page' => 100,
            ]),
        ];
    }

    protected function detectAttachmentFormat(string $filePath, ?string $originalName, ?string $mime): ?string
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
        $headers = $this->readCsvHeaders($filePath);
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
