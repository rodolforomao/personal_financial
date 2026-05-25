<?php

namespace Modules\Finance\Application\Services;

use Illuminate\Http\Request;
use Modules\Finance\Application\DTOs\DashboardFilter;
use Modules\Operations\Infrastructure\Models\Operation;

class DashboardFilterService
{
    public const SESSION_INCLUDE_ALL = 'dashboard.include_all_operations';

    public const SESSION_EXCLUDE_IDS = 'dashboard.exclude_operation_ids';

    public function fromSession(Request $request): DashboardFilter
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $excludeIds = $this->sanitizeExcludeIds(
            $workspaceId,
            (array) session(self::SESSION_EXCLUDE_IDS, []),
        );

        return new DashboardFilter(
            includeAllOperations: (bool) session(self::SESSION_INCLUDE_ALL, true),
            excludeOperationIds: $excludeIds,
        );
    }

    public function persist(Request $request): DashboardFilter
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $validated = $request->validate([
            'include_all_operations' => 'nullable|boolean',
            'exclude_operation_ids' => 'nullable|array',
            'exclude_operation_ids.*' => 'integer|min:1',
        ]);

        $includeAll = $request->boolean('include_all_operations');
        $excludeIds = $this->sanitizeExcludeIds(
            $workspaceId,
            $validated['exclude_operation_ids'] ?? [],
        );

        session([
            self::SESSION_INCLUDE_ALL => $includeAll,
            self::SESSION_EXCLUDE_IDS => $excludeIds,
        ]);

        return new DashboardFilter(
            includeAllOperations: $includeAll,
            excludeOperationIds: $excludeIds,
        );
    }

    /**
     * @param  array<int|string>  $ids
     * @return list<int>
     */
    protected function sanitizeExcludeIds(int $workspaceId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $valid = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($ids, $valid));
    }
}
