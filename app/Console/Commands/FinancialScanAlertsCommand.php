<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Alerts\Application\Services\AlertDetectionService;
use Modules\Core\Infrastructure\Models\Workspace;

class FinancialScanAlertsCommand extends Command
{
    protected $signature = 'financial:scan-alerts {--workspace= : ID do workspace (opcional)}';

    protected $description = 'Detecta alertas financeiros (contas, receitas, gastos, previsão)';

    public function handle(AlertDetectionService $alerts): int
    {
        $query = Workspace::query()->where('is_active', true);

        if ($this->option('workspace')) {
            $query->where('id', $this->option('workspace'));
        }

        foreach ($query->get() as $workspace) {
            $alerts->scan($workspace->id);
            $this->line("Alertas verificados: workspace #{$workspace->id}");
        }

        return self::SUCCESS;
    }
}
