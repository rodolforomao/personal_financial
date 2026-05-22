<?php

namespace App\Core\Support;

use App\Core\Contracts\ModuleInterface;

class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    protected array $modules = [];

    public function register(ModuleInterface $module): void
    {
        $this->modules[$module->name()] = $module;
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function get(string $name): ?ModuleInterface
    {
        return $this->modules[$name] ?? null;
    }
}
