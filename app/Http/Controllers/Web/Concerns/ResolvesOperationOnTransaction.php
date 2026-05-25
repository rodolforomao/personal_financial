<?php

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\Request;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Operations\Infrastructure\Models\OperationUnit;

trait ResolvesOperationOnTransaction
{
    /**
     * @return array{operation_id: ?int, operation_unit_id: ?int, company_id: ?int}
     */
    protected function resolveOperationFields(Request $request, int $workspaceId, ?int $fallbackCompanyId = null): array
    {
        $unitId = $request->filled('operation_unit_id') ? $request->integer('operation_unit_id') : null;
        $operationId = $request->filled('operation_id') ? $request->integer('operation_id') : null;

        if ($unitId) {
            $unit = OperationUnit::query()
                ->whereKey($unitId)
                ->whereHas('operation', fn ($q) => $q->where('workspace_id', $workspaceId))
                ->with('operation')
                ->firstOrFail();

            return [
                'operation_id' => $unit->operation_id,
                'operation_unit_id' => $unit->id,
                'company_id' => $unit->operation->company_id ?? $fallbackCompanyId,
            ];
        }

        if ($operationId) {
            $operation = Operation::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($operationId)
                ->firstOrFail();

            return [
                'operation_id' => $operation->id,
                'operation_unit_id' => null,
                'company_id' => $operation->company_id ?? $fallbackCompanyId,
            ];
        }

        return [
            'operation_id' => null,
            'operation_unit_id' => null,
            'company_id' => $fallbackCompanyId,
        ];
    }
}
