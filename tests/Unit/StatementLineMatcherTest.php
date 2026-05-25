<?php

namespace Tests\Unit;

use Modules\Finance\Application\Services\StatementLineMatcher;
use PHPUnit\Framework\TestCase;

class StatementLineMatcherTest extends TestCase
{
    public function test_scores_exact_date_and_description_higher(): void
    {
        $matcher = new StatementLineMatcher;

        $high = $matcher->scoreValues(
            '2026-05-21',
            'PIX Multifilmes Goiania',
            'Multifilmes',
            '2026-05-21',
            'PIX Multifilmes Goiania',
            'Multifilmes',
        );

        $low = $matcher->scoreValues(
            '2026-05-21',
            'PIX Multifilmes Goiania',
            'Multifilmes',
            '2026-05-19',
            'Outro texto',
            null,
        );

        $this->assertGreaterThan($low, $high);
        $this->assertGreaterThanOrEqual(80, $high);
    }
}
