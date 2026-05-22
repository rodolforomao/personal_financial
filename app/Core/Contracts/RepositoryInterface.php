<?php

namespace App\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function find(int|string $id): ?Model;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
