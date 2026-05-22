<?php

namespace App\Core\Contracts;

interface ModuleInterface
{
    public function name(): string;

    public function register(): void;

    public function boot(): void;
}
