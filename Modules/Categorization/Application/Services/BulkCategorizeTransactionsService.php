<?php

namespace Modules\Categorization\Application\Services;

use Illuminate\Support\Str;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;

class BulkCategorizeTransactionsService
{
    public function __construct(
        protected CategorizationService $categorization,
    ) {}

    /**
     * @return array{categorized: int, unchanged: int, total: int, operations_assigned: int}
     */
    public function run(int $workspaceId): array
    {
        return array_merge(
            $this->categorize($workspaceId),
            ['operations_assigned' => $this->assignOperationsFromCompany($workspaceId)],
        );
    }

    /**
     * @param  array<int>  $ruleIds
     * @return array{categorized: int, unchanged: int, total: int}
     */
    public function runForRules(int $workspaceId, array $ruleIds): array
    {
        $ruleIds = array_values(array_unique(array_filter($ruleIds)));

        if ($ruleIds === []) {
            return [
                'categorized' => 0,
                'unchanged' => 0,
                'total' => 0,
            ];
        }

        return $this->categorize($workspaceId, $ruleIds);
    }

    /**
     * @param  array<int>|null  $onlyRuleIds
     * @return array{categorized: int, unchanged: int, total: int}
     */
    protected function categorize(int $workspaceId, ?array $onlyRuleIds = null): array
    {
        $categorized = 0;
        $unchanged = 0;

        $query = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('category_id')
            ->orderBy('id');

        $total = (clone $query)->count();

        $query->each(function (Transaction $transaction) use ($workspaceId, $onlyRuleIds, &$categorized, &$unchanged) {
            $suggestion = $onlyRuleIds === null
                ? $this->categorization->suggest(
                    $workspaceId,
                    $transaction->description,
                    $transaction->counterparty,
                    $transaction->type,
                )
                : $this->categorization->suggestFromRules(
                    $workspaceId,
                    $transaction->description,
                    $transaction->counterparty,
                    $transaction->type,
                    $onlyRuleIds,
                );

            if (! $suggestion || empty($suggestion['category_id'])) {
                $unchanged++;

                return;
            }

            $update = [
                'category_id'               => $suggestion['category_id'],
                'categorization_confidence' => $suggestion['confidence'],
            ];
            if (! $transaction->operation_id && ! empty($suggestion['operation_id'])) {
                $update['operation_id'] = $suggestion['operation_id'];
            }
            if (! $transaction->company_id && ! empty($suggestion['company_id'])) {
                $update['company_id'] = $suggestion['company_id'];
            }
            $transaction->update($update);

            $categorized++;
        });

        return [
            'categorized' => $categorized,
            'unchanged' => $unchanged,
            'total' => $total,
        ];
    }

    /**
     * Vincula operações a transações sem operation_id usando quatro passagens:
     * 1. FK direta: operation.company_id = transaction.company_id
     * 2. Nome da empresa → nome da operação (fallback quando FK não está setada)
     * 3. Descrição/contraparte → nome da operação ou da empresa vinculada
     * 4. Regras de categorização com operation_id/company_id definidos
     *
     * @return array{assigned: int, via_company: int, via_description: int, via_rules: int}
     */
    public function runOperationAssignment(int $workspaceId): array
    {
        $allOps = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->with('company')
            ->get();

        if ($allOps->isEmpty()) {
            return ['assigned' => 0, 'via_company' => 0, 'via_description' => 0];
        }

        // Mapa company_id → Operation (passagem 1: FK direta)
        $byCompanyId = $allOps
            ->whereNotNull('company_id')
            ->keyBy('company_id');

        // Mapa company_id → Operation (passagem 2: nome empresa ↔ nome operação)
        $unmatchedCompanyIds = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('operation_id')
            ->whereNotNull('company_id')
            ->whereNotIn('company_id', $byCompanyId->keys())
            ->pluck('company_id')
            ->unique();

        $byCompanyName = collect();
        if ($unmatchedCompanyIds->isNotEmpty()) {
            $companies = Company::query()->whereIn('id', $unmatchedCompanyIds)->get();
            foreach ($companies as $company) {
                $needle = Str::lower(Str::ascii($company->name));
                $match = $allOps->first(fn (Operation $op) =>
                    Str::lower(Str::ascii($op->name)) === $needle ||
                    str_contains(Str::lower(Str::ascii($op->name)), $needle)
                );
                if ($match) {
                    $byCompanyName->put($company->id, $match);
                }
            }
        }

        $companyMap = $byCompanyId->union($byCompanyName);

        // Passagem 3: mapa de needles (nome operação + nome empresa) → Operation
        $needleMap = []; // string => Operation
        foreach ($allOps as $op) {
            $key = Str::lower(Str::ascii($op->name));
            if ($key !== '') {
                $needleMap[$key] = $op;
            }
            if ($op->company) {
                $companyKey = Str::lower(Str::ascii($op->company->name));
                if ($companyKey !== '' && ! isset($needleMap[$companyKey])) {
                    $needleMap[$companyKey] = $op;
                }
            }
        }
        // Ordena do mais longo para o mais curto para evitar matches parciais errados
        uksort($needleMap, fn ($a, $b) => strlen($b) - strlen($a));

        $viaCompany     = 0;
        $viaDescription = 0;
        $viaRules       = 0;

        Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('operation_id')
            ->each(function (Transaction $transaction) use (
                $workspaceId, $companyMap, $needleMap, &$viaCompany, &$viaDescription, &$viaRules
            ) {
                // Passagens 1 e 2: empresa (FK ou nome)
                if ($transaction->company_id && $companyMap->has($transaction->company_id)) {
                    $op = $companyMap->get($transaction->company_id);
                    $transaction->update(['operation_id' => $op->id]);
                    $viaCompany++;
                    return;
                }

                // Passagem 3: descrição / contraparte → nome operação ou empresa
                $haystack = Str::lower(Str::ascii(
                    trim($transaction->description . ' ' . ($transaction->counterparty ?? ''))
                ));
                foreach ($needleMap as $needle => $op) {
                    if ($needle !== '' && str_contains($haystack, $needle)) {
                        $update = ['operation_id' => $op->id];
                        if ($op->company_id && ! $transaction->company_id) {
                            $update['company_id'] = $op->company_id;
                        }
                        $transaction->update($update);
                        $viaDescription++;
                        return;
                    }
                }

                // Passagem 4: regras de categorização com operation_id/company_id
                $ruleSuggestion = $this->categorization->suggestOperationFromRules(
                    $workspaceId,
                    $transaction->description,
                    $transaction->counterparty,
                    $transaction->type,
                );
                if ($ruleSuggestion && (! empty($ruleSuggestion['operation_id']) || ! empty($ruleSuggestion['company_id']))) {
                    $update = [];
                    if (! empty($ruleSuggestion['operation_id'])) {
                        $update['operation_id'] = $ruleSuggestion['operation_id'];
                    }
                    if (! $transaction->company_id && ! empty($ruleSuggestion['company_id'])) {
                        $update['company_id'] = $ruleSuggestion['company_id'];
                    }
                    $transaction->update($update);
                    $viaRules++;
                }
            });

        return [
            'assigned'        => $viaCompany + $viaDescription + $viaRules,
            'via_company'     => $viaCompany,
            'via_description' => $viaDescription,
            'via_rules'       => $viaRules,
        ];
    }

    protected function assignOperationsFromCompany(int $workspaceId): int
    {
        // Passagem 1: operações que já têm company_id definido (link direto por FK)
        $byCompanyId = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('company_id')
            ->get()
            ->keyBy('company_id');

        // Passagem 2: empresas cujas transações ainda não têm operação e não
        // foram cobertas pela passagem 1 — tenta casar pelo nome da empresa
        // com o nome da operação (ex.: "Pessoa Física" → operação "Pessoa Física" ou "Geral")
        $unmatchedCompanyIds = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('operation_id')
            ->whereNotNull('company_id')
            ->whereNotIn('company_id', $byCompanyId->keys())
            ->pluck('company_id')
            ->unique();

        $byNameFallback = collect(); // company_id -> Operation

        if ($unmatchedCompanyIds->isNotEmpty()) {
            $companies = Company::query()
                ->whereIn('id', $unmatchedCompanyIds)
                ->get();

            $allOps = Operation::query()
                ->where('workspace_id', $workspaceId)
                ->get();

            foreach ($companies as $company) {
                $needle = Str::lower(Str::ascii($company->name));

                $match = $allOps->first(function (Operation $op) use ($needle) {
                    $haystack = Str::lower(Str::ascii($op->name));
                    return $haystack === $needle || str_contains($haystack, $needle);
                });

                if ($match) {
                    $byNameFallback->put($company->id, $match);
                }
            }
        }

        $map = $byCompanyId->union($byNameFallback);

        if ($map->isEmpty()) {
            return 0;
        }

        $assigned = 0;

        Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('operation_id')
            ->whereNotNull('company_id')
            ->whereIn('company_id', $map->keys())
            ->each(function (Transaction $transaction) use ($map, &$assigned) {
                $operation = $map->get($transaction->company_id);
                if ($operation) {
                    $transaction->update(['operation_id' => $operation->id]);
                    $assigned++;
                }
            });

        return $assigned;
    }

    public function countUncategorized(int $workspaceId): int
    {
        return Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('category_id')
            ->count();
    }
}
