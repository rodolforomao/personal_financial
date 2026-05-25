<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionStatus;
use App\Core\Enums\TransactionType;
use Modules\Finance\Application\Actions\CreateTransactionAction;
use Modules\Finance\Application\DTOs\CreateTransactionData;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;
use Modules\Finance\Infrastructure\Models\Transaction;

class StatementReconciliationService
{
    public function __construct(
        protected StatementLineMatcher $matcher,
        protected CreateTransactionAction $createTransaction,
        protected StatementTransactionDefaultsResolver $defaultsResolver,
    ) {}

    public function autoMatch(StatementImport $import): int
    {
        $matched = 0;

        $import->lines()
            ->visible()
            ->whereIn('match_status', [StatementLine::STATUS_UNMATCHED, StatementLine::STATUS_SUGGESTED])
            ->each(function (StatementLine $line) use ($import, &$matched) {
                $best = $this->matcher->findBestMatch(
                    $import->workspace_id,
                    $line->transaction_date->toDateString(),
                    $line->type,
                    (float) $line->amount,
                    $line->description,
                    $line->counterparty,
                );

                if ($best) {
                    $line->update([
                        'transaction_id' => $best['transaction_id'],
                        'match_status' => StatementLine::STATUS_SUGGESTED,
                        'match_score' => $best['score'],
                    ]);
                    $matched++;
                }
            });

        $import->refreshCounts();

        return $matched;
    }

    public function confirmMatch(StatementLine $line): void
    {
        if (! $line->transaction_id) {
            return;
        }

        $transaction = Transaction::query()->findOrFail($line->transaction_id);
        $metadata = $transaction->metadata ?? [];
        $metadata['statement_line_id'] = $line->id;
        $metadata['statement_import_id'] = $line->statement_import_id;

        $transaction->update([
            'status' => TransactionStatus::Reconciled,
            'metadata' => $metadata,
        ]);

        $line->update(['match_status' => StatementLine::STATUS_MATCHED]);
        $line->import->refreshCounts();
    }

    public function importAsTransaction(StatementLine $line, int $workspaceId): Transaction
    {
        $defaults = $this->defaultsResolver->resolve($line->import, $line);

        $transaction = $this->createTransaction->execute(new CreateTransactionData(
            workspaceId: $workspaceId,
            type: $line->type,
            amount: (float) $line->amount,
            description: $line->description,
            transactionDate: $line->transaction_date->toDateString(),
            status: TransactionStatus::Reconciled,
            counterparty: $line->counterparty,
            categoryId: $defaults['category_id'],
            fundingSource: $defaults['funding_source'],
            paymentMethod: $defaults['payment_method'],
            source: $line->import->format,
            metadata: [
                'statement_line_id' => $line->id,
                'statement_import_id' => $line->statement_import_id,
                'external_ref' => $line->external_ref,
            ],
        ));

        $line->update([
            'transaction_id' => $transaction->id,
            'match_status' => StatementLine::STATUS_IMPORTED,
        ]);

        $line->import->refreshCounts();

        return $transaction;
    }

    public function skipLine(StatementLine $line): void
    {
        $line->update(['match_status' => StatementLine::STATUS_SKIPPED]);
        $line->import->refreshCounts();
    }

    public function confirmAllSuggested(StatementImport $import): int
    {
        $count = 0;
        $import->lines()
            ->where('match_status', StatementLine::STATUS_SUGGESTED)
            ->whereNotNull('transaction_id')
            ->each(function (StatementLine $line) use (&$count) {
                $this->confirmMatch($line);
                $count++;
            });

        return $count;
    }

    public function importAllUnmatched(StatementImport $import): int
    {
        $count = 0;
        $import->lines()
            ->visible()
            ->where('match_status', StatementLine::STATUS_UNMATCHED)
            ->each(function (StatementLine $line) use ($import, &$count) {
                $this->importAsTransaction($line, $import->workspace_id);
                $count++;
            });

        if ($count > 0) {
            $import->update(['status' => StatementImport::STATUS_IMPORTED]);
        }

        return $count;
    }
}
