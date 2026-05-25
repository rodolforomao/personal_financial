<?php

namespace Modules\Categorization\Application\Services;

use Modules\Finance\Infrastructure\Models\Transaction;

class BulkCategorizeTransactionsService
{
    public function __construct(
        protected CategorizationService $categorization,
    ) {}

    /**
     * @return array{categorized: int, unchanged: int, total: int}
     */
    public function run(int $workspaceId): array
    {
        $categorized = 0;
        $unchanged = 0;

        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('category_id')
            ->orderBy('id');

        $total = (clone $query)->count();

        $query->each(function (Transaction $transaction) use ($workspaceId, &$categorized, &$unchanged) {
            $suggestion = $this->categorization->suggest(
                $workspaceId,
                $transaction->description,
                $transaction->counterparty,
                $transaction->type,
            );

            if (! $suggestion || empty($suggestion['category_id'])) {
                $unchanged++;

                return;
            }

            $transaction->update([
                'category_id' => $suggestion['category_id'],
                'categorization_confidence' => $suggestion['confidence'],
            ]);

            $categorized++;
        });

        return [
            'categorized' => $categorized,
            'unchanged' => $unchanged,
            'total' => $total,
        ];
    }

    public function countUncategorized(int $workspaceId): int
    {
        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('category_id')
            ->count();
    }
}
