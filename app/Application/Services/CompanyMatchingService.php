<?php

namespace App\Application\Services;

use Illuminate\Support\Str;
use Modules\Companies\Infrastructure\Models\Company;

/**
 * Resolve company by name with bidirectional substring match and fuzzy fallback.
 *
 * Normalisation strips common Brazilian legal suffixes (LTDA, S/A, etc.) so that
 * "CONVERGE ISP LTDA" matches a company registered as "Converge ISP".
 */
class CompanyMatchingService
{
    private const LEGAL_SUFFIXES = [
        'ltda me', 'ltda epp', 'ltda', 'eireli', 's/a', 'sa', 'me', 'epp',
        's.a.', 's.a', 'sociedade anonima', 'microempresa',
    ];

    private const FUZZY_THRESHOLD = 80;

    public function normalize(string $name): string
    {
        $name = Str::lower(Str::ascii(Str::squish($name)));

        foreach (self::LEGAL_SUFFIXES as $suffix) {
            if (str_ends_with($name, ' '.$suffix)) {
                $name = rtrim(substr($name, 0, -strlen(' '.$suffix)));
                break;
            }
        }

        return trim($name);
    }

    /**
     * Find a Company by name using multiple strategies in order:
     *   1. Exact match (after normalisation)
     *   2. Bidirectional substring (name ⊂ needle or needle ⊂ name)
     *   3. Fuzzy similarity >= FUZZY_THRESHOLD %
     */
    public function findByName(int $workspaceId, string $name): ?Company
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $needle = $this->normalize($name);

        $companies = Company::query()
            ->where('workspace_id', $workspaceId)
            ->get(['id', 'name']);

        $exact = null;
        $bidirectional = null;
        $fuzzyBest = null;
        $fuzzyScore = 0;

        foreach ($companies as $company) {
            $hay = $this->normalize($company->name);

            if ($hay === $needle) {
                return $company;
            }

            if ($bidirectional === null && (str_contains($needle, $hay) || str_contains($hay, $needle))) {
                $bidirectional = $company;
                continue;
            }

            if ($fuzzyBest === null || $fuzzyScore < self::FUZZY_THRESHOLD) {
                similar_text($needle, $hay, $pct);
                if ($pct >= self::FUZZY_THRESHOLD && $pct > $fuzzyScore) {
                    $fuzzyScore = $pct;
                    $fuzzyBest = $company;
                }
            }
        }

        return $bidirectional ?? $fuzzyBest;
    }
}
