<?php

namespace Modules\Intelligence\Presentation\Http\Controllers;

use App\Core\Exceptions\AiUnavailableException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Intelligence\Application\Services\FinancialIntelligenceService;
use Modules\Intelligence\Infrastructure\Models\AiConversation;
use Modules\Intelligence\Infrastructure\Models\AiMessage;

class AiAssistantController extends Controller
{
    public function ask(Request $request, FinancialIntelligenceService $service): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
        ]);

        $workspaceId = (int) $request->attributes->get('workspace_id');

        $conversation = isset($validated['conversation_id'])
            ? AiConversation::query()->findOrFail($validated['conversation_id'])
            : AiConversation::query()->create([
                'workspace_id' => $workspaceId,
                'user_id' => $request->user()->id,
                'context' => 'assistant',
            ]);

        AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['question'],
        ]);

        try {
            $answer = $service->assistantReply($workspaceId, $validated['question'], $request->user()->id);
        } catch (AiUnavailableException $e) {
            return response()->json([
                'message' => $e->userMessage(),
                'configure_url' => url('/ai/settings'),
            ], 422);
        }

        AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $answer,
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'answer' => $answer,
        ]);
    }
}
