<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionType;
use Carbon\Carbon;
use Modules\Finance\Infrastructure\Models\Transaction;

class StatementLineMatcher
{
    /**
     * @return array{transaction_id: int, score: int}|null
     */
    public function findBestMatch(
        int $workspaceId,
        string $date,
        TransactionType $type,
        float $amount,
        string $description,
        ?string $counterparty = null,
    ): ?array {
        $candidates = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', $type)
            ->whereBetween('transaction_date', [
                Carbon::parse($date)->subDay()->toDateString(),
                Carbon::parse($date)->addDay()->toDateString(),
            ])
            ->where('amount', $amount)
            ->whereNull('metadata->statement_line_id')
            ->limit(20)
            ->get();

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $transaction) {
            $score = $this->score($transaction, $date, $description, $counterparty);
            if ($score > $bestScore && $score >= 60) {
                $bestScore = $score;
                $best = ['transaction_id' => $transaction->id, 'score' => $score];
            }
        }

        return $best;
    }

    public function score(Transaction $transaction, string $lineDate, string $description, ?string $counterparty): int
    {
        return $this->scoreValues(
            $transaction->transaction_date->toDateString(),
            $transaction->description,
            $transaction->counterparty,
            $lineDate,
            $description,
            $counterparty,
        );
    }

    public function scoreValues(
        string $transactionDate,
        string $transactionDescription,
        ?string $transactionCounterparty,
        string $lineDate,
        string $lineDescription,
        ?string $lineCounterparty,
    ): int {
        $score = 50;

        if ($transactionDate === $lineDate) {
            $score += 25;
        }

        $lineDesc = mb_strtolower(trim($lineDescription));
        $txDesc = mb_strtolower(trim($transactionDescription));
        if ($lineDesc !== '' && (str_contains($txDesc, $lineDesc) || str_contains($lineDesc, $txDesc))) {
            $score += 20;
        }

        if ($lineCounterparty && $transactionCounterparty) {
            $cp = mb_strtolower($lineCounterparty);
            $txCp = mb_strtolower($transactionCounterparty);
            if (str_contains($txCp, $cp) || str_contains($cp, $txCp)) {
                $score += 15;
            }
        }

        return min(100, $score);
    }
}
