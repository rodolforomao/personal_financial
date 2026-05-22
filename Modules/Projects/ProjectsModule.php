<?php

namespace Modules\Projects;

use App\Core\Contracts\ModuleInterface;

class ProjectsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'projects';
    }

    public function register(): void {}

    public function boot(): void {}
}
