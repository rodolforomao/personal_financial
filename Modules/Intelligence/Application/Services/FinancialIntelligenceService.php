<?php

namespace Modules\Intelligence\Application\Services;

use App\Core\DTOs\AiRequestData;
use App\Core\DTOs\AiResponseData;
use App\Core\Exceptions\AiUnavailableException;
use App\Core\Support\FeatureFlag;
use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Application\Services\Providers\AiProviderManager;
use Modules\Intelligence\Infrastructure\Models\AiInsight;

class FinancialIntelligenceService
{
    public function __construct(
        protected AiProviderManager $providers,
        protected AiCredentialsResolver $credentials,
        protected AssistantFallbackService $fallback,
    ) {}

    public function analyzeWorkspace(int $workspaceId, ?int $userId = null): array
    {
        if (! FeatureFlag::enabled('ai_financial_analysis', $workspaceId)) {
            return [];
        }

        $context = app(FinancialContextBuilder::class)->build($workspaceId);
        $prompt = config('financial.ai_prompts.ecosystem_analysis');

        $response = $this->complete($workspaceId, $userId, new AiRequestData(
            prompt: $prompt,
            context: 'financial_analysis',
            systemPrompt: "Analyze this financial ecosystem JSON and return structured insights.\n\n{$context}",
            metadata: ['workspace_id' => $workspaceId, 'user_id' => $userId],
        ));

        return $this->persistInsights($workspaceId, $response->structured ?: json_decode($response->content, true) ?? []);
    }

    public function suggestCategory(int $workspaceId, string $description, ?string $counterparty, ?int $userId = null): ?array
    {
        if (! FeatureFlag::enabled('ai_categorization', $workspaceId)) {
            return null;
        }

        try {
            $response = $this->complete($workspaceId, $userId, new AiRequestData(
                prompt: "Categorize: description={$description}, counterparty={$counterparty}. Return JSON: category_slug, confidence.",
                context: 'categorization',
                systemPrompt: config('financial.ai_prompts.categorization'),
                metadata: ['workspace_id' => $workspaceId, 'user_id' => $userId],
            ));

            $parsed = json_decode($response->content, true);

            return is_array($parsed) ? $parsed : null;
        } catch (AiUnavailableException) {
            return null;
        }
    }

    public function assistantReply(int $workspaceId, string $question, ?int $userId = null): string
    {
        try {
            $context = app(FinancialContextBuilder::class)->build($workspaceId);

            $response = $this->complete($workspaceId, $userId, new AiRequestData(
                prompt: $question,
                context: 'assistant',
                systemPrompt: config('financial.ai_prompts.assistant')."\n\nContext:\n{$context}",
                metadata: ['workspace_id' => $workspaceId, 'user_id' => $userId],
            ));

            return $response->content;
        } catch (AiUnavailableException $e) {
            $local = $this->fallback->tryAnswer($workspaceId, $question);
            if ($local) {
                return $local;
            }

            throw $e;
        }
    }

    protected function complete(int $workspaceId, ?int $userId, AiRequestData $request): AiResponseData
    {
        $creds = $this->credentials->resolve($userId, $workspaceId);

        return $this->providers->driver($creds->provider)->complete(
            new AiRequestData(
                prompt: $request->prompt,
                context: $request->context,
                systemPrompt: $request->systemPrompt,
                metadata: array_merge($request->metadata, [
                    'api_key' => $creds->apiKey,
                    'model' => $creds->model,
                ]),
                temperature: $request->temperature,
                maxTokens: $request->maxTokens,
            )
        );
    }

    protected function persistInsights(int $workspaceId, array $insights): array
    {
        $created = [];

        foreach ($insights as $insight) {
            if (empty($insight['title'])) {
                continue;
            }

            $created[] = AiInsight::query()->create([
                'workspace_id' => $workspaceId,
                'type' => $insight['type'] ?? 'general',
                'severity' => $insight['severity'] ?? 'info',
                'title' => $insight['title'],
                'summary' => $insight['summary'] ?? '',
                'payload' => $insight['payload'] ?? [],
                'suggested_actions' => $insight['actions'] ?? [],
                'provider' => $insight['provider'] ?? config('financial.ai.default'),
                'detected_at' => now(),
            ]);
        }

        Log::channel('financial')->info('AI insights generated', [
            'workspace_id' => $workspaceId,
            'count' => count($created),
        ]);

        return $created;
    }
}
