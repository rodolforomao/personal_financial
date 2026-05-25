<?php

namespace App\Application\Services;

use Illuminate\Support\Str;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Operations\Infrastructure\Models\OperationUnit;

class ReceiptCaptionContextResolver
{
    /**
     * Interpreta legenda do comprovante (ex.: "Airbnb, residencial oliveiras, nubank, pix").
     *
     * @return array{
     *     category_id: ?int,
     *     category_slug: ?string,
     *     category_name: ?string,
     *     company_id: ?int,
     *     company_name: ?string,
     *     operation_id: ?int,
     *     operation_name: ?string,
     *     operation_unit_id: ?int,
     *     operation_unit_name: ?string
     * }
     */
    public function resolve(int $workspaceId, string $caption): array
    {
        $caption = trim($caption);
        if ($caption === '') {
            return $this->emptyResult();
        }

        $haystack = mb_strtolower($caption);
        $tokens = $this->tokens($caption);

        $categorySlug = $this->matchCategorySlug($haystack, $tokens);
        $category = $categorySlug
            ? Category::query()
                ->where('workspace_id', $workspaceId)
                ->where('slug', $categorySlug)
                ->first()
            : $this->resolveCategory($workspaceId, $tokens);

        $company = $this->resolveCompany($workspaceId, $haystack, $tokens);
        $operation = $this->resolveOperation($workspaceId, $haystack, $tokens, $company);
        $unit = $operation ? $this->resolveOperationUnit($operation, $haystack, $tokens) : null;

        if ($operation && ! $company && $operation->company_id) {
            $company = $operation->company;
        }

        return [
            'category_id' => $category?->id,
            'category_slug' => $category?->slug ?? $categorySlug,
            'category_name' => $category?->name,
            'company_id' => $company?->id,
            'company_name' => $company?->name,
            'operation_id' => $operation?->id,
            'operation_name' => $operation?->name,
            'operation_unit_id' => $unit?->id,
            'operation_unit_name' => $unit?->displayName(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function tokens(string $caption): array
    {
        $parts = preg_split('/[,;|]+/u', $caption) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            foreach ($this->tokenCandidates($part) as $t) {
                if ($t !== '' && ! $this->isIgnoredToken($t) && ! in_array($t, $tokens, true)) {
                    $tokens[] = $t;
                }
            }
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    protected function tokenCandidates(string $part): array
    {
        $token = trim($part);
        if ($token === '') {
            return [];
        }

        $candidates = [$token];
        $descriptor = '(?:receita|recebimento|entrada|despesa|despeza|gasto|sa[ií]da|categoria|empresa|opera[çc][aã]o|op|fonte|banco|contraparte|meio(?:\s+de\s+pagamento)?|forma(?:\s+de\s+pagamento)?|pagamento)';
        $clean = preg_replace('/^(?:'.$descriptor.')(?:\s*(?:\be\b|\/|\+|,)\s*(?:'.$descriptor.'))*\s+/iu', '', $token);
        $clean = trim((string) $clean);

        if ($clean !== '' && mb_strtolower($clean) !== mb_strtolower($token)) {
            $candidates[] = $clean;
        }

        return $candidates;
    }

    protected function isIgnoredToken(string $token): bool
    {
        $lower = mb_strtolower(trim($token));
        $ignored = (array) config('financial.receipt_caption.ignore_tokens', []);

        return in_array($lower, $ignored, true);
    }

    /**
     * @param  list<string>  $tokens
     */
    protected function matchCategorySlug(string $haystack, array $tokens): ?string
    {
        $keywords = (array) config('financial.receipt_caption.category_keywords', []);
        if ($keywords === []) {
            return null;
        }

        uksort($keywords, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($keywords as $needle => $slug) {
            $needleLower = mb_strtolower($needle);
            if (str_contains($haystack, $needleLower)) {
                return (string) $slug;
            }
        }

        foreach ($tokens as $token) {
            $tokenLower = mb_strtolower($token);
            foreach ($keywords as $needle => $slug) {
                if ($tokenLower === mb_strtolower($needle) || str_contains($tokenLower, mb_strtolower($needle))) {
                    return (string) $slug;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tokens
     */
    protected function resolveCategory(int $workspaceId, array $tokens): ?Category
    {
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 4) {
                continue;
            }
            $found = $this->findCategoryByName($workspaceId, $token);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tokens
     */
    protected function resolveCompany(int $workspaceId, string $haystack, array $tokens): ?Company
    {
        $aliases = (array) config('financial.receipt_caption.company_aliases', []);

        foreach ($aliases as $needle => $name) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                $found = $this->findCompanyByName($workspaceId, (string) $name);
                if ($found) {
                    return $found;
                }
            }
        }

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 4) {
                continue;
            }
            $found = $this->findCompanyByName($workspaceId, $token);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tokens
     */
    protected function resolveOperation(
        int $workspaceId,
        string $haystack,
        array $tokens,
        ?Company $company,
    ): ?Operation {
        $aliases = (array) config('financial.receipt_caption.operation_aliases', []);

        foreach ($aliases as $needle => $nameOrSlug) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                $found = $this->findOperation($workspaceId, (string) $nameOrSlug, $company);
                if ($found) {
                    return $found;
                }
            }
        }

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 4) {
                continue;
            }
            $found = $this->findOperation($workspaceId, $token, $company);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    public function findCategoryByName(int $workspaceId, string $needle): ?Category
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        $slug = Str::slug($needle);

        return Category::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($q) use ($needle, $slug) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($needle)])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($needle).'%']);
                if ($slug !== '') {
                    $q->orWhere('slug', $slug)
                        ->orWhere('slug', 'like', $slug.'%');
                }
            })
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 ELSE 1 END', [mb_strtolower($needle)])
            ->first();
    }

    public function findCompanyByName(int $workspaceId, string $needle): ?Company
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        return Company::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($needle)])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($needle).'%']);
            })
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 ELSE 1 END', [mb_strtolower($needle)])
            ->first();
    }

    public function findOperationByName(int $workspaceId, string $needle, ?Company $company = null): ?Operation
    {
        return $this->findOperation($workspaceId, $needle, $company);
    }

    protected function findOperation(int $workspaceId, string $needle, ?Company $company): ?Operation
    {
        $needle = trim($needle);
        if ($needle === '') {
            return null;
        }

        $slug = Str::slug($needle);

        $query = Operation::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($q) use ($needle, $slug) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($needle)])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($needle).'%']);
                if ($slug !== '') {
                    $q->orWhere('slug', $slug)
                        ->orWhere('slug', 'like', $slug.'%');
                }
            })
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 ELSE 1 END', [mb_strtolower($needle)]);

        if ($company) {
            $query->where(function ($q) use ($company) {
                $q->where('company_id', $company->id)
                    ->orWhereNull('company_id');
            });
        }

        return $query->first();
    }

    public function findOperationUnit(Operation $operation, string $needle): ?OperationUnit
    {
        return $this->resolveOperationUnit($operation, mb_strtolower($needle), $this->tokens($needle));
    }

    /**
     * @param  list<string>  $tokens
     */
    protected function resolveOperationUnit(Operation $operation, string $haystack, array $tokens): ?OperationUnit
    {
        $units = $operation->units()->where('is_active', true)->get();
        if ($units->isEmpty()) {
            return null;
        }

        foreach ($units as $unit) {
            $nameLower = mb_strtolower($unit->name);
            $codeLower = $unit->code ? mb_strtolower($unit->code) : null;
            if ($nameLower !== '' && str_contains($haystack, $nameLower)) {
                return $unit;
            }
            if ($codeLower !== '' && str_contains($haystack, $codeLower)) {
                return $unit;
            }
            if ($codeLower !== '' && preg_match('/\b'.preg_quote($codeLower, '/').'\b/u', $haystack)) {
                return $unit;
            }
        }

        foreach ($tokens as $token) {
            $tokenLower = mb_strtolower($token);
            if (preg_match('/^(apto|apt|apartamento|unidade)\s*\.?\s*(\d{1,4}[a-z]?)$/iu', $token, $m)) {
                $code = mb_strtolower($m[2]);
                foreach ($units as $unit) {
                    if ($unit->code && mb_strtolower($unit->code) === $code) {
                        return $unit;
                    }
                    if (str_contains(mb_strtolower($unit->name), $code)) {
                        return $unit;
                    }
                }
            }
            foreach ($units as $unit) {
                if ($unit->code && mb_strtolower($unit->code) === $tokenLower) {
                    return $unit;
                }
            }
        }

        return null;
    }

    /**
     * @return array{
     *     category_id: null,
     *     category_slug: null,
     *     category_name: null,
     *     company_id: null,
     *     company_name: null,
     *     operation_id: null,
     *     operation_name: null,
     *     operation_unit_id: null,
     *     operation_unit_name: null
     * }
     */
    protected function emptyResult(): array
    {
        return [
            'category_id' => null,
            'category_slug' => null,
            'category_name' => null,
            'company_id' => null,
            'company_name' => null,
            'operation_id' => null,
            'operation_name' => null,
            'operation_unit_id' => null,
            'operation_unit_name' => null,
        ];
    }
}
