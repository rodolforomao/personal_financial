<?php

namespace Modules\Alerts\Application\Services;

use App\Core\Enums\AlertSeverity;
use App\Core\Enums\TransactionType;
use Illuminate\Support\Carbon;
use Modules\Alerts\Application\Jobs\DispatchAlertJob;
use Modules\Alerts\Infrastructure\Models\Alert;
use Modules\Finance\Infrastructure\Models\RecurringItem;
use Modules\Finance\Infrastructure\Models\Transaction;

class AlertDetectionService
{
    public function scan(int $workspaceId): void
    {
        $this->detectUnpaidBills($workspaceId);
        $this->detectMissingRevenue($workspaceId);
        $this->detectAbnormalSpending($workspaceId);
        $this->detectNegativeForecast($workspaceId);
    }

    protected function detectUnpaidBills(int $workspaceId): void
    {
        $overdue = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', TransactionType::Expense)
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdue as $tx) {
            $this->createAlert($workspaceId, 'unpaid_bill', AlertSeverity::Warning,
                "Conta não paga: {$tx->description}",
                "Vencimento: {$tx->due_date?->format('d/m/Y')}, Valor: R$ {$tx->amount}",
                ['transaction_id' => $tx->id]
            );
        }
    }

    protected function detectMissingRevenue(int $workspaceId): void
    {
        $expected = RecurringItem::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', TransactionType::Income->value)
            ->where('is_active', true)
            ->where('next_due_at', '<', now()->subDays(3))
            ->get();

        foreach ($expected as $item) {
            $received = Transaction::query()
                ->where('workspace_id', $workspaceId)
                ->where('type', TransactionType::Income)
                ->where('company_id', $item->company_id)
                ->where('amount', $item->amount)
                ->where('transaction_date', '>=', now()->startOfMonth())
                ->exists();

            if (! $received) {
                $this->createAlert($workspaceId, 'missing_revenue', AlertSeverity::Critical,
                    "Receita não recebida: {$item->title}",
                    "Esperado: R$ {$item->amount}",
                    ['recurring_item_id' => $item->id]
                );
            }
        }
    }

    protected function detectAbnormalSpending(int $workspaceId): void
    {
        $current = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', TransactionType::Expense)
            ->where('transaction_date', '>=', now()->startOfMonth())
            ->sum('amount');

        $previous = Transaction::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', TransactionType::Expense)
            ->whereBetween('transaction_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->sum('amount');

        if ($previous > 0 && $current > ($previous * 1.3)) {
            $pct = round((($current - $previous) / $previous) * 100);
            $this->createAlert($workspaceId, 'abnormal_spending', AlertSeverity::Warning,
                'Gasto elevado detectado',
                "Gastos aumentaram {$pct}% em relação ao mês anterior.",
                ['current' => $current, 'previous' => $previous]
            );
        }
    }

    protected function detectNegativeForecast(int $workspaceId): void
    {
        $forecast = app(\Modules\Finance\Application\Services\ForecastService::class)
            ->generate($workspaceId);

        if ($forecast->projected_balance < 0) {
            $this->createAlert($workspaceId, 'negative_cashflow_forecast', AlertSeverity::Critical,
                'Previsão negativa de caixa',
                "Saldo projetado: R$ {$forecast->projected_balance} nos próximos {$forecast->horizon_days} dias.",
                ['forecast_id' => $forecast->id]
            );
        }
    }

    protected function createAlert(int $workspaceId, string $type, AlertSeverity $severity, string $title, string $message, array $context = []): void
    {
        $exists = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', $type)
            ->where('is_read', false)
            ->where('created_at', '>=', Carbon::today())
            ->exists();

        if ($exists) {
            return;
        }

        $alert = Alert::query()->create([
            'workspace_id' => $workspaceId,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'triggered_at' => now(),
        ]);

        DispatchAlertJob::dispatch($alert->id)->onQueue('notifications');
    }
}
