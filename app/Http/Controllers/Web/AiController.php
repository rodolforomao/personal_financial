<?php

namespace App\Http\Controllers\Web;

use App\Core\Exceptions\AiUnavailableException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Intelligence\Application\Jobs\RunFinancialAnalysisJob;
use Modules\Intelligence\Application\Services\AiCredentialsResolver;
use Modules\Intelligence\Application\Services\FinancialIntelligenceService;
use Modules\Intelligence\Infrastructure\Models\AiConversation;
use Modules\Intelligence\Infrastructure\Models\AiInsight;
use Modules\Intelligence\Infrastructure\Models\AiMessage;

class AiController extends Controller
{
    public function insights(Request $request): View
    {
        return view('ai.insights', [
            'insights' => AiInsight::query()
                ->where('workspace_id', $request->attributes->get('workspace_id'))
                ->orderByDesc('detected_at')
                ->paginate(20),
            'aiStatus' => app(AiCredentialsResolver::class)->status(
                $request->user()->id,
                (int) $request->attributes->get('workspace_id')
            ),
        ]);
    }

    public function assistant(Request $request, AiCredentialsResolver $resolver): View
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        $conversation = AiConversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $request->user()->id)
            ->where('context', 'assistant')
            ->latest()
            ->first();

        return view('ai.assistant', [
            'messages' => $conversation?->messages()->orderBy('id')->get() ?? collect(),
            'aiStatus' => $resolver->status($request->user()->id, $workspaceId),
        ]);
    }

    public function ask(Request $request, FinancialIntelligenceService $service): RedirectResponse
    {
        $validated = $request->validate(['question' => 'required|string|max:2000']);

        $workspaceId = (int) $request->attributes->get('workspace_id');
        $userId = $request->user()->id;

        $conversation = AiConversation::query()->firstOrCreate([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'context' => 'assistant',
        ], ['title' => 'Assistente']);

        AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['question'],
        ]);

        try {
            $answer = $service->assistantReply($workspaceId, $validated['question'], $userId);
            $flash = 'success';
            $flashMsg = str_contains($answer, '**Resposta local**')
                ? 'Resposta gerada com dados locais (IA não configurada ou indisponível).'
                : 'Resposta gerada.';
        } catch (AiUnavailableException $e) {
            $answer = $e->userMessage();
            $flash = 'warning';
            $flashMsg = 'Configure a IA para respostas completas.';
        } catch (\Throwable) {
            report($e);
            $answer = 'Não foi possível obter resposta da IA neste momento. Verifique **Inteligência → Configuração IA** ou tente mais tarde.';
            $flash = 'error';
            $flashMsg = 'Erro ao contactar o provedor de IA.';
        }

        AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $answer,
        ]);

        return back()->with($flash, $flashMsg);
    }

    public function analyze(Request $request, AiCredentialsResolver $resolver): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');

        if (! $resolver->status($request->user()->id, $workspaceId)['ready']) {
            return back()->with('warning', 'IA não configurada. Defina sua API key ou ative a IA do sistema em Configuração IA.');
        }

        RunFinancialAnalysisJob::dispatch($workspaceId, $request->user()->id)
            ->onQueue('ai');

        return back()->with('success', 'Análise financeira enfileirada.');
    }
}
