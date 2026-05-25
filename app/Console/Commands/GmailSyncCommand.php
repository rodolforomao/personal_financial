<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Infrastructure\Models\Workspace;
use Modules\Integrations\Application\Services\GmailEmailImportService;

class GmailSyncCommand extends Command
{
    protected $signature = 'gmail:sync {--workspace= : ID do workspace} {--limit=25 : Quantidade máxima de e-mails por workspace}';

    protected $description = 'Lê e-mails financeiros no Gmail conectado e cria transações automaticamente';

    public function handle(GmailEmailImportService $import): int
    {
        $query = Workspace::query()->where('is_active', true);
        if ($this->option('workspace')) {
            $query->whereKey((int) $this->option('workspace'));
        }

        $limit = (int) $this->option('limit');
        foreach ($query->get() as $workspace) {
            $result = $import->syncWorkspace((int) $workspace->id, $limit);
            if (! empty($result['error'])) {
                $this->warn("Workspace #{$workspace->id}: {$result['error']}");
                continue;
            }

            $this->info(
                "Workspace #{$workspace->id}: {$result['created']} criada(s), ".
                "{$result['skipped']} ignorada(s), {$result['scanned']} lida(s)."
            );
        }

        return self::SUCCESS;
    }
}
