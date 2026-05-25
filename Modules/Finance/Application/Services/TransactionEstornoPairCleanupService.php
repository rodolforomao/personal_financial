<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionType;
use App\Core\Support\DecimalComparer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Finance\Infrastructure\Models\Transaction;

/**
 * Remove pares anulados já importados: compra+estorno ou estornos duplicados (Uber/Inter).
 */
class TransactionEstornoPairCleanupService
{
    public function __construct(
        protected StatementNettedPairService $nettedPairs,
    ) {}

    /**
     * @return array{pair_count: int, transaction_count: int}
     */
    public function preview(int $workspaceId): array
    {
        $result = $this->collectPairs($workspaceId);

        return [
            'pair_count' => count($result['pairs']),
            'transaction_count' => count(array_unique($result['to_delete'])),
        ];
    }

    /**
     * @return array{removed: int, pair_count: int, pairs: list<array{kind: string, date: string, id_a: int, id_b: int, amount_a: float, amount_b: float, label_a: string, label_b: string}>}
     */
    public function removeNettedPairs(int $workspaceId, bool $dryRun = false): array
    {
        $result = $this->collectPairs($workspaceId);
        $toDelete = array_values(array_unique($result['to_delete']));

        if (! $dryRun && $toDelete !== []) {
            Transaction::query()->whereIn('id', $toDelete)->delete();
        }

        return [
            'removed' => count($toDelete),
            'pair_count' => count($result['pairs']),
            'pairs' => $result['pairs'],
        ];
    }

    /**
     * @return array{to_delete: list<int>, pairs: list<array{kind: string, date: string, id_a: int, id_b: int, amount_a: float, amount_b: float, label_a: string, label_b: string}>}
     */
    protected function collectPairs(int $workspaceId): array
    {
        $transactions = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('transaction_date')
            ->orderBy('amount')
            ->get();

        $toDelete = [];
        $pairs = [];

        foreach ($transactions->groupBy(fn (Transaction $t) => $t->transaction_date->toDateString()) as $date => $group) {
            $this->pairPurchaseAndEstornoOnDate(
                $group->filter(fn (Transaction $t) => ! in_array($t->id, $toDelete, true)),
                $toDelete,
                $pairs,
                $date,
                exactAmountOnly: true,
            );
            $this->pairPurchaseAndEstornoOnDate(
                $group->filter(fn (Transaction $t) => ! in_array($t->id, $toDelete, true)),
                $toDelete,
                $pairs,
                $date,
                exactAmountOnly: false,
            );
            $this->pairDuplicateEstornosOnDate(
                $group->filter(fn (Transaction $t) => ! in_array($t->id, $toDelete, true)),
                $toDelete,
                $pairs,
                $date,
            );
        }

        return ['to_delete' => $toDelete, 'pairs' => $pairs];
    }

    /**
     * @param  Collection<int, Transaction>  $group
     * @param  list<int>  $toDelete
     * @param  list<array{kind: string, date: string, id_a: int, id_b: int, amount_a: float, amount_b: float, label_a: string, label_b: string}>  $pairs
     */
    protected function pairPurchaseAndEstornoOnDate(
        Collection $group,
        array &$toDelete,
        array &$pairs,
        string $date,
        bool $exactAmountOnly,
    ): void {
        $purchases = $group->filter(fn (Transaction $t) => $this->transactionIsPurchase($t))->values();
        $refunds = $group->filter(fn (Transaction $t) => $this->transactionIsEstorno($t))->values();

        foreach ($purchases as $purchase) {
            if (in_array($purchase->id, $toDelete, true)) {
                continue;
            }

            $best = null;
            $bestDiff = null;

            foreach ($refunds as $refund) {
                if (in_array($refund->id, $toDelete, true)) {
                    continue;
                }

                $purchaseAmount = (float) $purchase->amount;
                $refundAmount = (float) $refund->amount;

                if ($exactAmountOnly) {
                    if ($this->amountsDiffer($purchaseAmount, $refundAmount)) {
                        continue;
                    }
                } elseif (! $this->nettedPairs->amountsWithinTolerance($purchaseAmount, $refundAmount)) {
                    continue;
                }

                $diff = abs($purchaseAmount - $refundAmount);
                if ($best === null || $diff < $bestDiff) {
                    $best = $refund;
                    $bestDiff = $diff;
                }
            }

            if ($best !== null) {
                $this->markPair($toDelete, $pairs, 'purchase_estorno', $date, $purchase, $best);
            }
        }
    }

    /**
     * @param  Collection<int, Transaction>  $group
     * @param  list<int>  $toDelete
     * @param  list<array{kind: string, date: string, id_a: int, id_b: int, amount_a: float, amount_b: float, label_a: string, label_b: string}>  $pairs
     */
    protected function pairDuplicateEstornosOnDate(
        Collection $group,
        array &$toDelete,
        array &$pairs,
        string $date,
    ): void {
        $estornos = $group->filter(fn (Transaction $t) => $this->transactionIsEstorno($t))->values();
        $used = [];

        foreach ($estornos as $i => $a) {
            if (isset($used[$i]) || in_array($a->id, $toDelete, true)) {
                continue;
            }

            $bestJ = null;
            $bestDiff = null;

            foreach ($estornos as $j => $b) {
                if ($j <= $i || isset($used[$j]) || in_array($b->id, $toDelete, true)) {
                    continue;
                }

                if (! $this->nettedPairs->amountsWithinTolerance((float) $a->amount, (float) $b->amount)) {
                    continue;
                }

                $diff = abs((float) $a->amount - (float) $b->amount);
                if ($bestJ === null || $diff < $bestDiff) {
                    $bestJ = $j;
                    $bestDiff = $diff;
                }
            }

            if ($bestJ !== null) {
                $b = $estornos[$bestJ];
                $this->markPair($toDelete, $pairs, 'duplicate_estorno', $date, $a, $b);
                $used[$i] = true;
                $used[$bestJ] = true;
            }
        }
    }

    /**
     * @param  list<int>  $toDelete
     * @param  list<array{kind: string, date: string, id_a: int, id_b: int, amount_a: float, amount_b: float, label_a: string, label_b: string}>  $pairs
     */
    protected function markPair(
        array &$toDelete,
        array &$pairs,
        string $kind,
        string $date,
        Transaction $a,
        Transaction $b,
    ): void {
        $toDelete[] = $a->id;
        $toDelete[] = $b->id;
        $pairs[] = [
            'kind' => $kind,
            'date' => $date,
            'id_a' => $a->id,
            'id_b' => $b->id,
            'amount_a' => (float) $a->amount,
            'amount_b' => (float) $b->amount,
            'label_a' => $this->pairLabel($a),
            'label_b' => $this->pairLabel($b),
        ];
    }

    public function transactionIsPurchase(Transaction $transaction): bool
    {
        if (! $this->isExpense($transaction)) {
            return false;
        }

        $haystack = $this->haystack($transaction);

        foreach (config('financial.statement_import.netted_pair.purchase_patterns') ?? [] as $pattern) {
            if (str_contains($haystack, Str::lower($pattern))) {
                return true;
            }
        }

        foreach (config('financial.statement_import.merchant_patterns.uber') ?? ['uber'] as $pattern) {
            if (str_contains($haystack, Str::lower($pattern))) {
                return true;
            }
        }

        return false;
    }

    public function transactionIsEstorno(Transaction $transaction): bool
    {
        return $this->isIncome($transaction)
            && $this->nettedPairs->matchesEstorno($transaction->description, $transaction->counterparty);
    }

    protected function isExpense(Transaction $transaction): bool
    {
        $type = $transaction->type;

        return $type === TransactionType::Expense
            || (is_string($type) && $type === TransactionType::Expense->value);
    }

    protected function isIncome(Transaction $transaction): bool
    {
        $type = $transaction->type;

        return $type === TransactionType::Income
            || (is_string($type) && $type === TransactionType::Income->value);
    }

    protected function amountsDiffer(float $a, float $b): bool
    {
        return DecimalComparer::differs($a, $b, 2);
    }

    protected function haystack(Transaction $transaction): string
    {
        return Str::lower(trim("{$transaction->description} {$transaction->counterparty}"));
    }

    protected function pairLabel(Transaction $transaction): string
    {
        $type = $transaction->type instanceof TransactionType
            ? $transaction->type->value
            : (string) $transaction->type;

        return $type.' #'.$transaction->id;
    }
}
