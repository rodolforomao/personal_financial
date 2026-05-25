<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\TransactionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Finance\Infrastructure\Models\StatementImport;
use Modules\Finance\Infrastructure\Models\StatementLine;

/**
 * Compra + estorno no mesmo dia (valor igual ou diferença mínima) — par anulado.
 * Dois estornos Uber no mesmo dia com valores próximos — também anulados (ruído do Inter).
 */
class StatementNettedPairService
{
    public function markNettedPairs(StatementImport $import): int
    {
        $lines = $import->lines()
            ->whereIn('match_status', [
                StatementLine::STATUS_UNMATCHED,
                StatementLine::STATUS_SUGGESTED,
            ])
            ->get();

        if ($lines->isEmpty()) {
            return 0;
        }

        $nettedIds = [];
        $marked = 0;

        foreach ($lines->groupBy(fn (StatementLine $line) => $line->transaction_date->toDateString()) as $group) {
            $active = $group->filter(fn (StatementLine $l) => ! in_array($l->id, $nettedIds, true));

            $marked += $this->pairPurchaseAndEstorno($active, $nettedIds, exactAmountOnly: true);
            $active = $group->filter(fn (StatementLine $l) => ! in_array($l->id, $nettedIds, true));
            $marked += $this->pairPurchaseAndEstorno($active, $nettedIds, exactAmountOnly: false);
            $active = $group->filter(fn (StatementLine $l) => ! in_array($l->id, $nettedIds, true));
            $marked += $this->pairDuplicateEstornos($active, $nettedIds);
        }

        if ($marked > 0) {
            StatementLine::query()
                ->whereIn('id', $nettedIds)
                ->update(['match_status' => StatementLine::STATUS_NETTED, 'match_score' => null]);
            $import->refreshCounts();
        }

        return $marked;
    }

    public function matchesEstorno(string $description, ?string $counterparty = null): bool
    {
        $haystack = Str::lower(trim("{$description} {$counterparty}"));

        foreach (config('financial.statement_import.netted_pair.estorno_patterns') ?? [] as $pattern) {
            if (str_contains($haystack, Str::lower($pattern))) {
                return true;
            }
        }

        return false;
    }

    public function amountsWithinTolerance(float $a, float $b): bool
    {
        $maxDiff = (float) config('financial.statement_import.netted_pair.max_amount_diff', 1.0);

        return abs($a - $b) <= $maxDiff;
    }

    public function isPurchase(StatementLine $line): bool
    {
        if ($line->type !== TransactionType::Expense) {
            return false;
        }

        $haystack = $this->haystack($line);

        foreach (config('financial.statement_import.netted_pair.purchase_patterns') ?? [] as $pattern) {
            if (str_contains($haystack, Str::lower($pattern))) {
                return true;
            }
        }

        return false;
    }

    public function isEstorno(StatementLine $line): bool
    {
        return $line->type === TransactionType::Income && $this->matchesEstorno($line->description, $line->counterparty);
    }

    /**
     * @param  Collection<int, StatementLine>  $lines
     * @param  list<int>  $nettedIds
     */
    protected function pairPurchaseAndEstorno(Collection $lines, array &$nettedIds, bool $exactAmountOnly): int
    {
        $purchases = $lines->filter(fn (StatementLine $l) => $this->isPurchase($l))->values();
        $refunds = $lines->filter(fn (StatementLine $l) => $this->isEstorno($l))->values();

        $marked = 0;

        foreach ($purchases as $purchase) {
            $best = null;
            $bestDiff = null;

            foreach ($refunds as $refund) {
                if (in_array($refund->id, $nettedIds, true)) {
                    continue;
                }

                $purchaseAmount = (float) $purchase->amount;
                $refundAmount = (float) $refund->amount;

                if ($exactAmountOnly) {
                    if ($purchaseAmount !== $refundAmount) {
                        continue;
                    }
                } elseif (! $this->amountsWithinTolerance($purchaseAmount, $refundAmount)) {
                    continue;
                }

                $diff = abs($purchaseAmount - $refundAmount);
                if ($best === null || $diff < $bestDiff) {
                    $best = $refund;
                    $bestDiff = $diff;
                }
            }

            if ($best !== null) {
                $nettedIds[] = $purchase->id;
                $nettedIds[] = $best->id;
                $marked += 2;
            }
        }

        return $marked;
    }

    /**
     * Dois estornos (receita) no mesmo dia com valores próximos — típico Uber/Inter.
     *
     * @param  Collection<int, StatementLine>  $lines
     * @param  list<int>  $nettedIds
     */
    protected function pairDuplicateEstornos(Collection $lines, array &$nettedIds): int
    {
        $estornos = $lines->filter(fn (StatementLine $l) => $this->isEstorno($l))->values();
        $marked = 0;
        $used = [];

        foreach ($estornos as $i => $a) {
            if (in_array($a->id, $nettedIds, true) || isset($used[$i])) {
                continue;
            }

            $bestJ = null;
            $bestDiff = null;

            foreach ($estornos as $j => $b) {
                if ($j <= $i || isset($used[$j]) || in_array($b->id, $nettedIds, true)) {
                    continue;
                }

                if (! $this->amountsWithinTolerance((float) $a->amount, (float) $b->amount)) {
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
                $nettedIds[] = $a->id;
                $nettedIds[] = $b->id;
                $used[$i] = true;
                $used[$bestJ] = true;
                $marked += 2;
            }
        }

        return $marked;
    }

    protected function haystack(StatementLine $line): string
    {
        return Str::lower(trim("{$line->description} {$line->counterparty}"));
    }
}
