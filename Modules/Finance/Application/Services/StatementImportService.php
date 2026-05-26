<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use App\Models\User;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;

class StatementImportService
{
    public function __construct(
        protected CreateTransactionAction $createTransaction,
        protected StatementReconciliationService $reconciliation,
        protected StatementTransactionDefaultsResolver $defaultsResolver,
        protected StatementNettedPairService $nettedPairs,
    ) {}

    public function parseOfx(
        int $workspaceId,
        ?User $user,
        string $filePath,
        string $originalName,
        ?string $bankSlug = null,
    ): StatementImport {
        $content = file_get_contents($filePath) ?: '';
        $detectedBank = $bankSlug ?: $this->defaultsResolver->detectBankSlugFromOfxContent($content);

        $import = StatementImport::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => $user?->id,
            'original_name' => $originalName,
            'format' => 'ofx',
            'status' => StatementImport::STATUS_PENDING,
            'settings' => $detectedBank ? ['bank_slug' => $detectedBank] : null,
        ]);

        if (preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                $amount = $this->extractTag($block, 'TRNAMT');
                if ($amount === null) {
                    continue;
                }

                $type = (float) $amount >= 0 ? TransactionType::Income : TransactionType::Expense;
                $fitId = $this->extractTag($block, 'FITID');

                StatementLine::query()->create([
                    'statement_import_id' => $import->id,
                    'transaction_date' => $this->parseOfxDate($this->extractTag($block, 'DTPOSTED')),
                    'amount' => abs((float) $amount),
                    'type' => $type,
                    'description' => $this->extractTag($block, 'MEMO')
                        ?? $this->extractTag($block, 'NAME')
                        ?? 'Lançamento OFX',
                    'counterparty' => $this->extractTag($block, 'NAME'),
                    'external_ref' => $fitId,
                    'match_status' => StatementLine::STATUS_UNMATCHED,
                ]);
            }
        }

        $this->finalizeImport($import);

        return $import->fresh(['lines' => fn ($q) => $q->visible()->with('transaction')]);
    }

    /**
     * @param  array{amount: string, date: string, description?: string, counterparty?: string}  $mapping
     */
    public function parseCsv(
        int $workspaceId,
        ?User $user,
        string $filePath,
        string $originalName,
        array $mapping,
    ): StatementImport {
        $import = StatementImport::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => $user?->id,
            'original_name' => $originalName,
            'format' => 'csv',
            'status' => StatementImport::STATUS_PENDING,
            'settings' => ['mapping' => $mapping],
        ]);

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle) ?: [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            $rawAmount = $data[$mapping['amount']] ?? '0';
            $amount = $this->parseCsvAmount($rawAmount);
            $type = $amount >= 0 ? TransactionType::Income : TransactionType::Expense;

            StatementLine::query()->create([
                'statement_import_id' => $import->id,
                'transaction_date' => $this->parseCsvDate($data[$mapping['date']] ?? now()->toDateString()),
                'amount' => abs($amount),
                'type' => $type,
                'description' => $data[$mapping['description'] ?? ''] ?? 'Lançamento CSV',
                'counterparty' => isset($mapping['counterparty']) ? ($data[$mapping['counterparty']] ?? null) : null,
                'match_status' => StatementLine::STATUS_UNMATCHED,
            ]);
        }

        fclose($handle);

        $this->finalizeImport($import);

        return $import->fresh(['lines' => fn ($q) => $q->visible()->with('transaction')]);
    }

    protected function finalizeImport(StatementImport $import): void
    {
        $this->nettedPairs->markNettedPairs($import);
        $this->reconciliation->autoMatch($import);
        $import->refreshCounts();
    }

    /**
     * @return list<string>
     */
    public function readCsvHeaders(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle) ?: [];
        fclose($handle);

        return array_values(array_filter(array_map('trim', $header)));
    }

    protected function parseCsvAmount(string $raw): float
    {
        $raw = trim($raw);
        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }

    protected function parseCsvDate(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match(
            '/(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?/u',
            $raw,
            $m
        )) {
            return $this->formatDateTime(
                (int) $m[3],
                (int) $m[2],
                (int) $m[1],
                isset($m[4]) ? (int) $m[4] : 0,
                isset($m[5]) ? (int) $m[5] : 0,
                isset($m[6]) ? (int) $m[6] : 0,
            );
        }

        if (preg_match(
            '/(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{1,2}):(\d{2})(?::(\d{2}))?)?/u',
            $raw,
            $m
        )) {
            return $this->formatDateTime(
                (int) $m[1],
                (int) $m[2],
                (int) $m[3],
                isset($m[4]) ? (int) $m[4] : 0,
                isset($m[5]) ? (int) $m[5] : 0,
                isset($m[6]) ? (int) $m[6] : 0,
            );
        }

        return now()->format('Y-m-d H:i:s');
    }

    protected function extractTag(string $block, string $tag): ?string
    {
        return preg_match("/<{$tag}>([^<]+)/", $block, $m) ? trim($m[1]) : null;
    }

    protected function parseOfxDate(?string $date): string
    {
        if (! $date) {
            return now()->format('Y-m-d H:i:s');
        }

        $digits = preg_replace('/\D/', '', $date) ?? '';
        if (strlen($digits) < 8) {
            return now()->format('Y-m-d H:i:s');
        }

        return $this->formatDateTime(
            (int) substr($digits, 0, 4),
            (int) substr($digits, 4, 2),
            (int) substr($digits, 6, 2),
            strlen($digits) >= 10 ? (int) substr($digits, 8, 2) : 0,
            strlen($digits) >= 12 ? (int) substr($digits, 10, 2) : 0,
            strlen($digits) >= 14 ? (int) substr($digits, 12, 2) : 0,
        );
    }

    protected function formatDateTime(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): string
    {
        return sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d',
            $year,
            max(1, min(12, $month)),
            max(1, min(31, $day)),
            max(0, min(23, $hour)),
            max(0, min(59, $minute)),
            max(0, min(59, $second)),
        );
    }
}
