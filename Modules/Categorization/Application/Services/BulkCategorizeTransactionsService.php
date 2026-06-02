<?php

namespace Modules\Categorization\Application\Services;

use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;

class BulkCategorizeTransactionsService
{
    public function __construct(
        protected CategorizationService $categorization,
    ) {}

    /**
     * @return array{categorized: int, unchanged: int, total: int, operations_assigned: int}
     */
    public function run(int $workspaceId): array
    {
        return array_merge(
            $this->categorize($workspaceId),
            ['operations_assigned' => $this->assignOperationsFromCompany($workspaceId)],
        );
    }

    /**
     * @param  array<int>  $ruleIds
     * @return array{categorized: int, unchanged: int, total: int}
     */
    public function runForRules(int $workspaceId, array $ruleIds): array
    {
        $ruleIds = array_values(array_unique(array_filter($ruleIds)));

        if ($ruleIds === []) {
            return [
                'categorized' => 0,
                'unchanged' => 0,
                'total' => 0,
            ];
        }

        return $this->categorize($workspaceId, $ruleIds);
    }

    /**
     * @param  array<int>|null  $onlyRuleIds
     * @return array{categorized: int, unchanged: int, total: int}
     */
    protected function categorize(int $workspaceId, ?array $onlyRuleIds = null): array
    {
        $categorized = 0;
        $unchanged = 0;

        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('category_id')
            ->orderBy('id');

        $total = (clone $query)->count();

        $query->each(function (Transaction $transaction) use ($workspaceId, $onlyRuleIds, &$categorized, &$unchanged) {
            $suggestion = $onlyRuleIds === null
                ? $this->categorization->suggest(
                    $workspaceId,
                    $transaction->description,
                    $transaction->counterparty,
                    $transaction->type,
                )
                : $this->categorization->suggestFromRules(
                    $workspaceId,
                    $transaction->description,
                    $transaction->counterparty,
                    $transaction->type,
                    $onlyRuleIds,
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

    protected function assignOperationsFromCompany(int $workspaceId): int
    {
        $operations = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('company_id')
            ->get()
            ->keyBy('company_id');

        if ($operations->isEmpty()) {
            return 0;
        }

        $assigned = 0;

        Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('operation_id')
            ->whereNotNull('company_id')
            ->whereIn('company_id', $operations->keys())
            ->each(function (Transaction $transaction) use ($operations, &$assigned) {
                $operation = $operations->get($transaction->company_id);
                if ($operation) {
                    $transaction->update(['operation_id' => $operation->id]);
                    $assigned++;
                }
            });

        return $assigned;
    }

    public function countUncategorized(int $workspaceId): int
    {
        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('category_id')
            ->count();
    }
}
