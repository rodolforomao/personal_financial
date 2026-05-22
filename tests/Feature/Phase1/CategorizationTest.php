<?php

namespace Tests\Feature\Phase1;

use Modules\Categorization\Application\Services\CategorizationService;
use Modules\Categorization\Infrastructure\Models\Category;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class CategorizationTest extends TestCase
{
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
    }

    public function test_default_pattern_resolves_to_category_id(): void
    {
        $suggestion = app(CategorizationService::class)->suggest(
            $this->workspace->id,
            'Assinatura mensal',
            'OpenAI'
        );

        $this->assertNotNull($suggestion);
        $this->assertArrayHasKey('category_id', $suggestion);

        $category = Category::query()->find($suggestion['category_id']);
        $this->assertSame('ia', $category->slug);
        $this->assertGreaterThanOrEqual(80, $suggestion['confidence']);
    }
}
