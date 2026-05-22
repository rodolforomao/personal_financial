<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionType;
use Modules\Finance\Infrastructure\Models\Transaction;

class TransactionDeduplicationService
{
    public function exists(
        int $workspaceId,
        TransactionType $type,
        float $amount,
        string $transactionDate,
        string $description,
        ?string $externalFingerprint = null,
    ): bool {
        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', $type)
            ->where('amount', $amount)
            ->whereDate('transaction_date', $transactionDate);

        if ($externalFingerprint) {
            $byFingerprint = (clone $query)
                ->where('metadata->telegram_fingerprint', $externalFingerprint)
                ->exists();

            if ($byFingerprint) {
                return true;
            }
        }

        $snippet = mb_substr(trim($description), 0, 48);

        return $query
            ->where('description', 'like', '%'.$snippet.'%')
            ->exists();
    }
}
