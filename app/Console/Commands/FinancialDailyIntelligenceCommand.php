<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Alerts\Application\Services\AlertDetectionService;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Finance\Application\Services\CashFlowService;
use Modules\Finance\Application\Services\ForecastService;
use Modules\Intelligence\Application\Jobs\RunFinancialAnalysisJob;
use Modules\Intelligence\Application\Jobs\RunObservabilityAnalysisJob;

class FinancialDailyIntelligenceCommand extends Command
{
    protected $signature = 'financial:daily {--workspace= : ID do workspace (opcional)}';

    protected $description = 'Executa rotina diária: alertas, snapshot de caixa, previsão e jobs de IA';

    public function handle(
        AlertDetectionService $alerts,
        CashFlowService $cashFlow,
        ForecastService $forecast,
    ): int {
        $query = Workspace::query()->where('is_active', true);

        if ($this->option('workspace')) {
            $query->where('id', $this->option('workspace'));
        }

        $workspaces = $query->get();

        foreach ($workspaces as $workspace) {
            $this->info("Workspace #{$workspace->id} — {$workspace->name}");

            $alerts->scan($workspace->id);
            $cashFlow->snapshot($workspace->id);
            $forecast->generate($workspace->id);
            RunFinancialAnalysisJob::dispatch($workspace->id)->onQueue('ai');
            RunObservabilityAnalysisJob::dispatch($workspace->id)->onQueue('ai');
        }

        $this->info('Rotina concluída para '.$workspaces->count().' workspace(s).');

        return self::SUCCESS;
    }
}
