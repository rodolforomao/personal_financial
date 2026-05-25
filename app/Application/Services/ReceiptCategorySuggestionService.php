<?php

namespace App\Application\Services;

use App\Core\Enums\TransactionType;
use Modules\Categorization\Application\Services\CategorizationService;
use Modules\Categorization\Infrastructure\Models\Category;

class ReceiptCategorySuggestionService
{
    public function __construct(
        protected CategorizationService $categorization,
    ) {}

    /**
     * @return array{
     *     optional: bool,
     *     recommended: ?array{category_id: int, name: string, slug: string, confidence: float, source: string},
     *     message: string
     * }
     */
    public function forTransaction(
        int $workspaceId,
        string $description,
        ?string $counterparty = null,
        ?TransactionType $type = null,
        ?string $ocrCategorySlug = null,
    ): array {
        $description = trim($description);
        $counterparty = trim((string) $counterparty);

        $recommended = null;

        if ($ocrCategorySlug) {
            $fromOcr = Category::query()
                ->where('workspace_id', $workspaceId)
                ->where('slug', $ocrCategorySlug)
                ->first();

            if ($fromOcr && $this->categoryMatchesType($fromOcr, $type)) {
                $recommended = $this->formatCategory($fromOcr, 90.0, 'ocr');
            }
        }

        if (! $recommended && ($description !== '' || $counterparty !== '')) {
            $suggestion = $this->categorization->suggest(
                $workspaceId,
                $description,
                $counterparty !== '' ? $counterparty : null,
                $type,
            );

            if ($suggestion && ! empty($suggestion['category_id'])) {
                $category = Category::query()->find($suggestion['category_id']);
                if ($category) {
                    $recommended = $this->formatCategory(
                        $category,
                        (float) ($suggestion['confidence'] ?? 80),
                        (string) ($suggestion['source'] ?? 'pattern'),
                    );
                }
            }
        }

        return [
            'optional' => true,
            'recommended' => $recommended,
            'message' => $this->buildMessage($recommended),
        ];
    }

    protected function formatCategory(Category $category, float $confidence, string $source): array
    {
        return [
            'category_id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'confidence' => round($confidence, 1),
            'source' => $source,
        ];
    }

    protected function categoryMatchesType(Category $category, ?TransactionType $type): bool
    {
        if ($type === null || $type === TransactionType::Transfer) {
            return true;
        }

        $expected = $type === TransactionType::Income ? 'income' : 'expense';

        return $category->type === $expected;
    }

    protected function buildMessage(?array $recommended): string
    {
        if (! $recommended) {
            return 'Categoria opcional. Escolha uma lista abaixo ou deixe em branco.';
        }

        $sourceLabel = match ($recommended['source']) {
            'rule' => 'regra cadastrada',
            'ocr' => 'leitura do comprovante',
            'default_pattern' => 'padrão do sistema',
            'ai', 'pattern' => 'análise automática',
            default => 'sugestão automática',
        };

        return sprintf(
            'Sugerimos **%s** (%s, confiança %.0f%%). Você pode aceitar, escolher outra ou salvar sem categoria.',
            $recommended['name'],
            $sourceLabel,
            $recommended['confidence'],
        );
    }
}
