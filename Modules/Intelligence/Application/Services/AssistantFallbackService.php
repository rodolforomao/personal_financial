<?php

namespace Modules\Intelligence\Application\Services;

use Illuminate\Support\Str;
use Modules\Categorization\Infrastructure\Models\Category;
use Modules\Finance\Infrastructure\Models\Transaction;

class AssistantFallbackService
{
    public function tryAnswer(int $workspaceId, string $question): ?string
    {
        $q = Str::lower($question);

        if (Str::contains($q, ['ia', 'openai', 'chatgpt', 'claude', 'inteligência artificial'])) {
            return $this->spendingOnAi($workspaceId);
        }

        if (Str::contains($q, ['fluxo', 'caixa', '90 dias', 'previsão'])) {
            return $this->cashFlowSummary($workspaceId);
        }

        if (Str::contains($q, ['receita', 'despesa', 'gasto', 'mês'])) {
            return $this->monthSummary($workspaceId);
        }

        return null;
    }

    protected function spendingOnAi(int $workspaceId): string
    {
        $categoryIds = Category::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('slug', ['ia', 'ferramentas'])
            ->pluck('id');

        $start = now()->startOfMonth();

        $byCategory = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->where('status', 'confirmed')
            ->where('transaction_date', '>=', $start)
            ->whereIn('category_id', $categoryIds)
            ->sum('amount');

        $patterns = ['openai', 'chatgpt', 'claude', 'anthropic', 'cursor'];
        $byCounterparty = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->where('status', 'confirmed')
            ->where('transaction_date', '>=', $start)
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhere('counterparty', 'like', "%{$p}%")
                        ->orWhere('description', 'like', "%{$p}%");
                }
            })
            ->sum('amount');

        $total = max($byCategory, $byCounterparty);
        $month = now()->translatedFormat('F Y');

        return "**Resposta local** (sem API de IA configurada)\n\n"
            ."No mês atual ({$month}), seus gastos relacionados a IA somam aproximadamente **R$ "
            .number_format((float) $total, 2, ',', '.')
            ."**.\n\n"
            .'Para respostas mais completas, configure uma API key em **Inteligência → Configuração IA**.';
    }

    protected function monthSummary(int $workspaceId): string
    {
        $start = now()->startOfMonth();
        $income = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'income')
            ->where('status', 'confirmed')
            ->where('transaction_date', '>=', $start)
            ->sum('amount');
        $expense = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'expense')
            ->where('status', 'confirmed')
            ->where('transaction_date', '>=', $start)
            ->sum('amount');

        return "**Resposta local**\n\n"
            .'Este mês: receitas **R$ '.number_format((float) $income, 2, ',', '.')
            .'**, despesas **R$ '.number_format((float) $expense, 2, ',', '.')
            .'**, líquido **R$ '.number_format((float) $income - (float) $expense, 2, ',', '.')
            ."**.\n\nConfigure a IA em **Configuração IA** para análises detalhadas.";
    }

    protected function cashFlowSummary(int $workspaceId): string
    {
        $forecast = app(\Modules\Finance\Application\Services\ForecastService::class)->generate($workspaceId);
        $cash = app(\Modules\Finance\Application\Services\CashFlowService::class)->dashboard($workspaceId);

        return "**Resposta local**\n\n"
            .'Fluxo líquido do mês: **R$ '.number_format((float) $cash['current_month']->net_cash_flow, 2, ',', '.')
            ."**.\n"
            .'Previsão 90 dias — saldo projetado: **R$ '.number_format((float) $forecast->projected_balance, 2, ',', '.')
            .'**, risco: **'.$forecast->risk_level."**.\n\n"
            .'Ative a API de IA para recomendações personalizadas.';
    }
}
