<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Infrastructure\Models\Transaction;

/**
 * Detecta pares/grupos de transações possivelmente duplicadas dentro de um workspace.
 *
 * Graus de confiança:
 *  - high   : mesmo valor + data 0–1 dias + mesmo tipo + descrição/contraparte semelhante
 *           : ou mesmo external_id não-nulo
 *           : ou dois source=clt_salary com mesmo clt_salary_id
 *  - medium : mesmo valor + data ≤ 3 dias + mesmo tipo + descrição/contraparte semelhante
 *  - low    : mesmo valor + data ≤ 7 dias + mesmo tipo (sem texto)
 *
 * Dismissal: IDs dispensados são armazenados em Transaction.metadata.dismissed_duplicates[].
 */
class DuplicateTransactionService
{
    private const DATE_HIGH   = 1;
    private const DATE_MEDIUM = 3;
    private const DATE_LOW    = 7;

    /** Máximo de pares a retornar (para não sobrecarregar a UI). */
    private const MAX_PAIRS = 60;

    /**
     * @return Collection<int, array{
     *     id: string,
     *     reason: string,
     *     confidence: string,
     *     transactions: Collection<int, Transaction>
     * }>
     */
    public function findGroups(int $workspaceId): Collection
    {
        $rawPairs = $this->queryCandidatePairs($workspaceId);

        if ($rawPairs->isEmpty()) {
            return collect();
        }

        $allIds = $rawPairs->flatMap(fn ($p) => [$p->id1, $p->id2])->unique()->values();

        /** @var Collection<int, Transaction> $txMap */
        $txMap = Transaction::query()
            ->whereIn('id', $allIds)
            ->with(['category', 'company'])
            ->get()
            ->keyBy('id');

        $groups  = collect();
        $seen    = [];

        foreach ($rawPairs as $pair) {
            $key = min($pair->id1, $pair->id2).'_'.max($pair->id1, $pair->id2);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $t1 = $txMap->get($pair->id1);
            $t2 = $txMap->get($pair->id2);

            if (! $t1 || ! $t2) {
                continue;
            }

            if ($this->isDismissed($t1, $t2)) {
                continue;
            }

            [$reason, $confidence] = $this->classify($t1, $t2, (int) $pair->date_diff);

            if ($reason === null) {
                continue;
            }

            $groups->push([
                'id'           => $key,
                'reason'       => $reason,
                'confidence'   => $confidence,
                'transactions' => collect([$t1, $t2])->sortBy('transaction_date'),
            ]);
        }

        // Adiciona duplicatas CLT (não capturadas pelo JOIN por valor/data)
        $cltGroups = $this->findCltDuplicates($workspaceId, $txMap, $seen);
        $groups = $groups->concat($cltGroups);

        return $groups
            ->sortBy(fn ($g) => match ($g['confidence']) { 'high' => 0, 'medium' => 1, default => 2 })
            ->values()
            ->take(self::MAX_PAIRS);
    }

    public function countGroups(int $workspaceId): int
    {
        return $this->findGroups($workspaceId)->count();
    }

    /** Adiciona o id da transação "irmã" na lista de dismissals de ambas as transações. */
    public function dismiss(int $workspaceId, int $id1, int $id2): void
    {
        $transactions = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', [$id1, $id2])
            ->get();

        foreach ($transactions as $tx) {
            $otherId = $tx->id === $id1 ? $id2 : $id1;
            $meta    = (array) ($tx->metadata ?? []);
            $dismissed = array_values(array_unique(array_merge($meta['dismissed_duplicates'] ?? [], [$otherId])));
            $tx->update(['metadata' => array_merge($meta, ['dismissed_duplicates' => $dismissed])]);
        }
    }

    // ── Internals ──────────────────────────────────────────────────────────────

    private function queryCandidatePairs(int $workspaceId): Collection
    {
        return collect(DB::select("
            SELECT
                t1.id   AS id1,
                t2.id   AS id2,
                ABS(DATEDIFF(t1.transaction_date, t2.transaction_date)) AS date_diff,
                t1.external_id AS ext1,
                t2.external_id AS ext2
            FROM transactions t1
            INNER JOIN transactions t2
                ON  t1.id < t2.id
                AND t1.workspace_id  = t2.workspace_id
                AND t1.type          = t2.type
                AND t1.amount        = t2.amount
                AND ABS(DATEDIFF(t1.transaction_date, t2.transaction_date)) <= :days
                AND t1.deleted_at IS NULL
                AND t2.deleted_at IS NULL
            WHERE t1.workspace_id = :ws
              AND t1.deleted_at IS NULL
            ORDER BY t1.transaction_date DESC
            LIMIT :lim
        ", [
            'ws'   => $workspaceId,
            'days' => self::DATE_LOW,
            'lim'  => self::MAX_PAIRS * 3,
        ]));
    }

    /**
     * @return array{string|null, string}  [reason, confidence]
     */
    private function classify(Transaction $t1, Transaction $t2, int $dateDiff): array
    {
        // External ID idêntico → importação duplicada
        if ($t1->external_id && $t2->external_id && $t1->external_id === $t2->external_id) {
            return ['Mesmo external_id — possível importação duplicada', 'high'];
        }

        // Recurring item idêntico → comportamento esperado do sistema, ignorar
        if ($t1->recurring_item_id && $t1->recurring_item_id === $t2->recurring_item_id) {
            return [null, 'low'];
        }

        $textMatch = $this->textsAreSimilar($t1, $t2);

        if ($dateDiff === 0 && $textMatch) {
            return ['Mesmo valor, mesma data e texto semelhante', 'high'];
        }

        if ($dateDiff <= self::DATE_HIGH && $textMatch) {
            return ['Mesmo valor, datas com 1 dia de diferença e texto semelhante', 'high'];
        }

        if ($dateDiff <= self::DATE_MEDIUM && $textMatch) {
            return ['Mesmo valor, datas próximas ('.$dateDiff.' dias) e texto semelhante', 'medium'];
        }

        if ($dateDiff === 0) {
            return ['Mesmo valor e mesma data (descrições diferentes)', 'medium'];
        }

        if ($dateDiff <= self::DATE_LOW) {
            return ['Mesmo valor, datas com '.$dateDiff.' dias de diferença', 'low'];
        }

        return [null, 'low'];
    }

    private function textsAreSimilar(Transaction $t1, Transaction $t2): bool
    {
        $normalize = fn (string $s): string => mb_strtolower(trim($s));

        // Descrições iguais
        if ($normalize($t1->description) === $normalize($t2->description)) {
            return true;
        }

        // Contrapartes iguais (quando ambas preenchidas)
        if ($t1->counterparty && $t2->counterparty
            && $normalize($t1->counterparty) === $normalize($t2->counterparty)) {
            return true;
        }

        // Uma descrição contém a outra (ex.: "Fatura Nubank" vs "Fatura Nubank novembro")
        $d1 = $normalize($t1->description);
        $d2 = $normalize($t2->description);
        if (mb_strlen($d1) >= 6 && mb_strlen($d2) >= 6) {
            if (str_contains($d2, $d1) || str_contains($d1, $d2)) {
                return true;
            }
        }

        // Mesma empresa cadastrada
        if ($t1->company_id && $t1->company_id === $t2->company_id) {
            return true;
        }

        return false;
    }

    private function isDismissed(Transaction $t1, Transaction $t2): bool
    {
        $d1 = (array) (($t1->metadata ?? [])['dismissed_duplicates'] ?? []);
        $d2 = (array) (($t2->metadata ?? [])['dismissed_duplicates'] ?? []);

        return in_array($t2->id, $d1, false) || in_array($t1->id, $d2, false);
    }

    /**
     * Detecta dois lançamentos CLT para o mesmo salário (mesmo clt_salary_id no metadata).
     *
     * @param  array<string, bool>  $seen  Pares já processados (evita duplicação de grupos)
     */
    private function findCltDuplicates(int $workspaceId, Collection $txMap, array &$seen): Collection
    {
        $cltTxs = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('source', 'clt_salary')
            ->whereNotNull('metadata')
            ->get(['id', 'metadata', 'transaction_date', 'amount', 'description', 'type', 'counterparty', 'company_id', 'category_id', 'source', 'external_id', 'recurring_item_id']);

        $bySalary = $cltTxs->groupBy(fn ($tx) => (string) (($tx->metadata ?? [])['clt_salary_id'] ?? 0))
            ->filter(fn ($group, $key) => $key !== '0' && $group->count() > 1);

        $groups = collect();

        foreach ($bySalary as $salaryId => $txs) {
            // Agrupa por mês de referência dentro do mesmo salário
            $byMonth = $txs->groupBy(fn ($tx) => (string) (($tx->metadata ?? [])['reference_month'] ?? 'unknown'))
                ->filter(fn ($g) => $g->count() > 1);

            foreach ($byMonth as $month => $monthTxs) {
                $list = $monthTxs->values();
                $t1   = $txMap->get($list[0]->id) ?? $list[0];
                $t2   = $txMap->get($list[1]->id) ?? $list[1];

                $key = min($t1->id, $t2->id).'_'.max($t1->id, $t2->id);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                if ($this->isDismissed($t1, $t2)) {
                    continue;
                }

                $monthLabel = $month !== 'unknown'
                    ? \Carbon\Carbon::parse($month)->translatedFormat('F/Y')
                    : 'mês desconhecido';

                $groups->push([
                    'id'           => $key,
                    'reason'       => "Dois lançamentos de salário CLT para o mesmo período ({$monthLabel})",
                    'confidence'   => 'high',
                    'transactions' => collect([$t1, $t2])->sortBy('transaction_date'),
                ]);
            }
        }

        return $groups;
    }
}
