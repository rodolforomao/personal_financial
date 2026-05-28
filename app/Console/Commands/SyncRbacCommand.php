<?php

namespace App\Console\Commands;

use App\Services\RbacBootstrap;
use Illuminate\Console\Command;

class SyncRbacCommand extends Command
{
    protected $signature = 'rbac:sync';

    protected $description = 'Cria permissões e papéis padrão (Spatie)';

    public function handle(): int
    {
        RbacBootstrap::sync();
        $this->info('RBAC sincronizado.');

        return self::SUCCESS;
    }
}
