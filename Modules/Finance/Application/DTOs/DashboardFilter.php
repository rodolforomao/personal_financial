<?php

namespace Modules\Finance\Application\DTOs;

final class DashboardFilter
{
    /**
     * @param  list<int>  $excludeOperationIds
     */
    public function __construct(
        public bool $includeAllOperations = true,
        public array $excludeOperationIds = [],
    ) {}

    public static function consolidated(): self
    {
        return new self(includeAllOperations: false);
    }

    public static function allOperations(): self
    {
        return new self(includeAllOperations: true);
    }

    /**
     * @return list<int>
     */
    public function normalizedExcludeIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $this->excludeOperationIds),
            fn (int $id) => $id > 0,
        )));
    }
}
