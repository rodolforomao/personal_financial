<?php

namespace Modules\Finance\Application\Services;

use App\Core\Enums\CompanyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;

class DataHygieneService
{
    /** Empresas de negócio próprio que devem ser tipo Own. */
    private const OWN_COMPANY_NAMES = [
        'Residencial Oliveiras',
    ];

    public function audit(int $workspaceId): array
    {
        $geral = $this->geralOperation($workspaceId);

        return [
            'without_operation' => $this->countWithoutOperation($workspaceId),
            'without_unit_in_ops_with_units' => $this->countMissingUnit($workspaceId),
            'geral_operation' => $geral ? [
                'id' => $geral->id,
                'name' => $geral->name,
                'transaction_count' => $geral->transactions()->count(),
                'exclude_from_main_dashboard' => $geral->exclude_from_main_dashboard,
            ] : null,
            'wrong_company_types' => $this->wrongCompanyTypes($workspaceId),
            'operations_with_units' => Operation::query()
                ->where('workspace_id', $workspaceId)
                ->whereHas('units')
                ->withCount('units')
                ->orderBy('name')
                ->get()
                ->map(fn (Operation $op) => [
                    'id' => $op->id,
                    'name' => $op->name,
                    'units_count' => $op->units_count,
                ])
                ->all(),
        ];
    }

    public function countWithoutOperation(int $workspaceId): int
    {
        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('operation_id')
            ->count();
    }

    public function countMissingUnit(int $workspaceId): int
    {
        return $this->missingUnitQuery($workspaceId)->count();
    }

    public function missingUnitQuery(int $workspaceId): Builder
    {
        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('operation_id')
            ->whereNull('operation_unit_id')
            ->whereHas('operation', fn (Builder $q) => $q
                ->where('workspace_id', $workspaceId)
                ->whereHas('units'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function withoutOperationQuery(int $workspaceId, array $filters = []): Builder
    {
        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('operation_id')
            ->with(['category', 'company']);

        if (! empty($filters['description'])) {
            $query->where('description', 'like', '%'.$filters['description'].'%');
        }

        if (! empty($filters['company_id'])) {
            $query->where('company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @return list<array{id: int, name: string, current: string, suggested: string}>
     */
    public function wrongCompanyTypes(int $workspaceId): array
    {
        $issues = [];

        foreach (self::OWN_COMPANY_NAMES as $name) {
            $company = Company::query()
                ->where('workspace_id', $workspaceId)
                ->where('name', $name)
                ->first();

            if (! $company || $company->type === CompanyType::Own) {
                continue;
            }

            $issues[] = [
                'id' => $company->id,
                'name' => $company->name,
                'current' => $company->type->label(),
                'suggested' => CompanyType::Own->label(),
            ];
        }

        return $issues;
    }

    public function fixKnownCompanyTypes(int $workspaceId): int
    {
        $fixed = 0;

        foreach (self::OWN_COMPANY_NAMES as $name) {
            $updated = Company::query()
                ->where('workspace_id', $workspaceId)
                ->where('name', $name)
                ->where('type', '!=', CompanyType::Own->value)
                ->update(['type' => CompanyType::Own->value]);

            $fixed += $updated;
        }

        return $fixed;
    }

    public function showGeralOnConsolidatedDashboard(int $workspaceId): bool
    {
        $geral = $this->geralOperation($workspaceId);

        if (! $geral) {
            return false;
        }

        if ($geral->exclude_from_main_dashboard) {
            $geral->update(['exclude_from_main_dashboard' => false]);
        }

        return true;
    }

    /** Transações da operação “Geral” passam a não ter operação (visão pessoal no consolidado). */
    public function releaseGeralTransactionsToPersonal(int $workspaceId): int
    {
        $geral = $this->geralOperation($workspaceId);

        if (! $geral) {
            return 0;
        }

        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('operation_id', $geral->id)
            ->update([
                'operation_id' => null,
                'operation_unit_id' => null,
            ]);
    }

    public function geralOperation(int $workspaceId): ?Operation
    {
        return Operation::query()
            ->where('workspace_id', $workspaceId)
            ->where(function (Builder $q) {
                $q->where('slug', 'geral')
                    ->orWhereRaw('LOWER(name) = ?', ['geral']);
            })
            ->first();
    }

    /**
     * Unidades cujo nome coincide com empresa cadastrada (possível confusão Liquidx × sócios).
     *
     * @return Collection<int, array{operation: string, unit: string, company: string}>
     */
    public function suspiciousOperationUnits(int $workspaceId): Collection
    {
        $companyNames = Company::query()
            ->where('workspace_id', $workspaceId)
            ->pluck('name')
            ->map(fn (string $n) => mb_strtolower(trim($n)))
            ->filter()
            ->unique()
            ->values();

        $warnings = collect();

        Operation::query()
            ->where('workspace_id', $workspaceId)
            ->with('units')
            ->each(function (Operation $operation) use ($companyNames, $warnings) {
                foreach ($operation->units as $unit) {
                    $unitName = mb_strtolower(trim($unit->name));
                    foreach ($companyNames as $companyName) {
                        if ($unitName === $companyName
                            || str_contains($unitName, $companyName)
                            || str_contains($companyName, $unitName)) {
                            if ($operation->company?->name !== $unit->name) {
                                $warnings->push([
                                    'operation' => $operation->name,
                                    'unit' => $unit->displayName(),
                                    'company' => $unit->name,
                                ]);
                            }
                            break;
                        }
                    }
                }
            });

        return $warnings->unique(fn (array $w) => $w['operation'].'|'.$w['unit'])->values();
    }
}
