<?php

namespace Modules\Intelligence;

use App\Core\Contracts\ModuleInterface;

class IntelligenceModule implements ModuleInterface
{
    public function name(): string
    {
        return 'intelligence';
    }

    public function register(): void
    {
        app()->singleton(
            \Modules\Intelligence\Application\Services\Providers\AiProviderManager::class
        );
    }

    public function boot(): void {}
}
