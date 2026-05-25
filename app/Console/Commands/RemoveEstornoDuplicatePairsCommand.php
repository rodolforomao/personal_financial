<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Finance\Application\Services\TransactionEstornoPairCleanupService;
use Modules\Finance\Infrastructure\Models\Transaction;

class RemoveEstornoDuplicatePairsCommand extends Command
{
    protected $signature = 'transactions:remove-estorno-pairs
                            {--workspace= : ID do workspace (padrão: workspace com mais candidatos)}
                            {--dry-run : Apenas lista os pares, sem excluir}
                            {--explain-date= : Mostra por que lançamentos de uma data não formam par (YYYY-MM-DD)}';

    protected $description = 'Exclui pares anulados: compra+estorno ou estornos duplicados no mesmo dia';

    public function handle(TransactionEstornoPairCleanupService $cleanup): int
    {
        $workspace = $this->resolveWorkspace();

        if (! $workspace) {
            $this->error('Nenhum workspace encontrado.');

            return self::FAILURE;
        }

        $this->line("Workspace: #{$workspace->id} ({$workspace->name})");

        if ($explainDate = $this->option('explain-date')) {
            $this->explainDate($cleanup, (int) $workspace->id, (string) $explainDate);

            return self::SUCCESS;
        }

        $preview = $cleanup->preview((int) $workspace->id);
        $this->line("Pares detectados: {$preview['pair_count']} ({$preview['transaction_count']} lançamentos)");

        $dryRun = (bool) $this->option('dry-run');
        $result = $cleanup->removeNettedPairs((int) $workspace->id, $dryRun);

        if ($result['pairs'] === []) {
            $this->warn('Nenhum par encontrado.');
            $this->line('Use --explain-date=2026-05-10 para ver compra/estorno na data.');
            $this->line('Confira --workspace=ID se tiver mais de um workspace.');

            return self::SUCCESS;
        }

        foreach ($result['pairs'] as $pair) {
            $kind = $pair['kind'] === 'purchase_estorno' ? 'compra+estorno' : 'estorno duplicado';
            $this->line(sprintf(
                '[%s] %s — %s R$ %s + %s R$ %s',
                $kind,
                $pair['date'],
                $pair['label_a'],
                number_format($pair['amount_a'], 2, ',', '.'),
                $pair['label_b'],
                number_format($pair['amount_b'], 2, ',', '.'),
            ));
        }

        if ($dryRun) {
            $this->warn('Dry-run: '.$result['removed'].' transação(ões) seriam excluídas.');
        } else {
            $this->info('Excluídas '.$result['removed'].' transação(ões) em '.$result['pair_count'].' par(es).');
            $this->line('Compras sem estorno no mesmo valor (ex.: Uber R$ 31,93) permanecem.');
        }

        return self::SUCCESS;
    }

    protected function resolveWorkspace(): ?Workspace
    {
        $workspaceId = $this->option('workspace');
        if ($workspaceId) {
            return Workspace::query()->find($workspaceId);
        }

        $bestId = Transaction::query()
            ->selectRaw('workspace_id')
            ->get()
            ->groupBy('workspace_id')
            ->map(fn ($rows, $wsId) => app(TransactionEstornoPairCleanupService::class)->preview((int) $wsId)['transaction_count'])
            ->sortDesc()
            ->keys()
            ->first();

        if ($bestId) {
            return Workspace::query()->find($bestId);
        }

        return Workspace::query()->first();
    }

    protected function explainDate(
        TransactionEstornoPairCleanupService $cleanup,
        int $workspaceId,
        string $date,
    ): void {
        $rows = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereDate('transaction_date', $date)
            ->orderBy('amount')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("Nenhum lançamento em {$date} neste workspace.");

            return;
        }

        $this->info("Lançamentos em {$date}:");
        foreach ($rows as $tx) {
            $purchase = $cleanup->transactionIsPurchase($tx) ? 'compra' : '—';
            $estorno = $cleanup->transactionIsEstorno($tx) ? 'estorno' : '—';
            $this->line(sprintf(
                '  #%d %s R$ %s | compra:%s estorno:%s | %s',
                $tx->id,
                $tx->type instanceof \App\Core\Enums\TransactionType ? $tx->type->value : $tx->type,
                number_format((float) $tx->amount, 2, ',', '.'),
                $purchase,
                $estorno,
                mb_substr($tx->description, 0, 55),
            ));
        }

        $preview = $cleanup->preview($workspaceId);
        $this->newLine();
        $this->line("Total de pares no workspace: {$preview['pair_count']} ({$preview['transaction_count']} lançamentos).");
    }
}
