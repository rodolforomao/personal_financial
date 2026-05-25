<?php

namespace App\Providers;

use App\Core\Support\ModuleRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Alerts\AlertsModule;
use Modules\Categorization\CategorizationModule;
use Modules\Companies\CompaniesModule;
use Modules\Core\CoreModule;
use Modules\Finance\FinanceModule;
use Modules\Integrations\IntegrationsModule;
use Modules\Intelligence\IntelligenceModule;
use Modules\OCR\OCRModule;
use Modules\Operations\OperationsModule;
use Modules\Projects\ProjectsModule;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $registry = new ModuleRegistry;

        foreach ($this->modules() as $module) {
            $registry->register($module);
            $module->register();
        }

        $this->app->instance(ModuleRegistry::class, $registry);
    }

    public function boot(): void
    {
        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            $module->boot();
        }
    }

    protected function modules(): array
    {
        return [
            new CoreModule,
            new FinanceModule,
            new CompaniesModule,
            new ProjectsModule,
            new OperationsModule,
            new CategorizationModule,
            new OCRModule,
            new IntelligenceModule,
            new AlertsModule,
            new IntegrationsModule,
        ];
    }
}
