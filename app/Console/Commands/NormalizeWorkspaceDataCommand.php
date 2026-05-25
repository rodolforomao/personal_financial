<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Finance\Application\Services\DataHygieneService;

class NormalizeWorkspaceDataCommand extends Command
{
    protected $signature = 'financial:normalize-workspace
                            {--workspace= : ID do workspace (padrão: primeiro ativo)}
                            {--fix-companies : Corrige tipos de empresa conhecidos}
                            {--geral-dashboard : Exibe operação Geral no dashboard consolidado}
                            {--release-geral : Remove operação_id das transações na operação Geral}
                            {--all : Executa todas as correções acima}';

    protected $description = 'Normaliza dados do workspace (tipos de empresa, operação Geral, escopo pessoal)';

    public function handle(DataHygieneService $hygiene): int
    {
        $workspaceId = $this->resolveWorkspaceId();

        if ($workspaceId === null) {
            $this->error('Nenhum workspace encontrado.');

            return self::FAILURE;
        }

        $runAll = $this->option('all');

        if ($runAll || $this->option('fix-companies')) {
            $n = $hygiene->fixKnownCompanyTypes($workspaceId);
            $this->info("Empresas corrigidas: {$n}");
        }

        if ($runAll || $this->option('geral-dashboard')) {
            $ok = $hygiene->showGeralOnConsolidatedDashboard($workspaceId);
            $this->info($ok ? 'Operação Geral visível no consolidado.' : 'Operação Geral não encontrada.');
        }

        if ($runAll || $this->option('release-geral')) {
            $n = $hygiene->releaseGeralTransactionsToPersonal($workspaceId);
            $this->info("Transações liberadas da operação Geral: {$n}");
        }

        if (! $runAll
            && ! $this->option('fix-companies')
            && ! $this->option('geral-dashboard')
            && ! $this->option('release-geral')) {
            $audit = $hygiene->audit($workspaceId);
            $this->table(
                ['Indicador', 'Valor'],
                [
                    ['Sem operação', $audit['without_operation']],
                    ['Em operação sem unidade (op. com aptos)', $audit['without_unit_in_ops_with_units']],
                    ['Tipos de empresa incorretos', count($audit['wrong_company_types'])],
                    ['Operação Geral', $audit['geral_operation']['name'] ?? '—'],
                ],
            );
            $this->line('Use --all ou flags individuais para aplicar correções.');

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }

    protected function resolveWorkspaceId(): ?int
    {
        if ($id = $this->option('workspace')) {
            return (int) $id;
        }

        return Workspace::query()->where('is_active', true)->value('id');
    }
}
