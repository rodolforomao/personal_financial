<?php

namespace App\Application\Services;

use App\Core\Support\LogFileReader;
use App\Core\Support\LogInterpreter;
use Illuminate\Support\Collection;
use Modules\Alerts\Infrastructure\Models\Alert;

class ObservabilityDashboardService
{
    public function __construct(
        protected LogFileReader $logReader,
        protected LogInterpreter $interpreter,
    ) {}

    /**
     * @param  array{level?: string, search?: string, file?: string, days?: int}  $filters
     * @return array<string, mixed>
     */
    public function build(int $workspaceId, bool $includeSystemLogs, array $filters = []): array
    {
        $days = (int) ($filters['days'] ?? 7);
        $since = now()->subDays(max(1, min($days, 30)));

        $alerts = Alert::query()
            ->where('workspace_id', $workspaceId)
            ->where('triggered_at', '>=', $since)
            ->orderByDesc('triggered_at')
            ->limit(100)
            ->get();

        $alertEvents = $alerts->map(function (Alert $alert) {
            $interpretation = $this->interpreter->interpretAlert($alert);

            return [
                'kind' => 'alert',
                'id' => 'alert-'.$alert->id,
                'datetime' => $alert->triggered_at,
                'level' => strtoupper($alert->severity->value),
                'title' => $alert->title,
                'message' => $alert->message,
                'is_read' => $alert->is_read,
                'is_sent' => $alert->is_sent,
                'type' => $alert->type,
                'category' => $interpretation['category'],
                'interpretation_title' => $interpretation['title'],
                'hint' => $interpretation['hint'],
                'priority' => $interpretation['priority'],
                'raw' => null,
                'file' => null,
            ];
        });

        $logEvents = collect();
        if ($includeSystemLogs) {
            $logs = $this->logReader->parseRecent(
                fileName: $filters['file'] ?? null,
                maxEntries: 200,
                level: $filters['level'] ?? null,
                search: $filters['search'] ?? null,
                workspaceId: null,
            )->filter(fn ($e) => $e['datetime']->gte($since));

            $logEvents = $logs->map(function (array $entry) {
                $interpretation = $this->interpreter->interpretLogEntry($entry);

                return [
                    'kind' => 'log',
                    'id' => 'log-'.md5($entry['raw'] ?? $entry['message']),
                    'datetime' => $entry['datetime'],
                    'level' => $entry['level'],
                    'title' => $interpretation['title'],
                    'message' => $entry['message'],
                    'is_read' => null,
                    'is_sent' => null,
                    'type' => $entry['channel'],
                    'category' => $interpretation['category'],
                    'interpretation_title' => $interpretation['title'],
                    'hint' => $interpretation['hint'],
                    'priority' => $interpretation['priority'],
                    'raw' => $entry['raw'],
                    'file' => $entry['file'],
                    'context' => $entry['context'],
                ];
            });
        }

        $timeline = $alertEvents
            ->concat($logEvents)
            ->sortByDesc(fn ($e) => $e['datetime']->timestamp)
            ->values();

        $insights = $this->buildInsights($timeline, $alerts);

        return [
            'timeline' => $timeline,
            'insights' => $insights,
            'summary' => $this->buildSummary($timeline, $alerts),
            'logFiles' => $includeSystemLogs ? $this->logReader->discoverFiles() : [],
            'alerts' => $alerts,
        ];
    }

    protected function buildSummary(Collection $timeline, $alerts): array
    {
        $logs = $timeline->where('kind', 'log');

        return [
            'total_events' => $timeline->count(),
            'unread_alerts' => $alerts->where('is_read', false)->count(),
            'critical_logs' => $logs->filter(fn ($e) => in_array($e['level'], ['ERROR', 'CRITICAL', 'EMERGENCY'], true))->count(),
            'warnings_logs' => $logs->filter(fn ($e) => $e['level'] === 'WARNING')->count(),
            'telegram_issues' => $timeline->where('category', 'telegram')->count(),
            'ai_issues' => $timeline->where('category', 'ia')->count(),
        ];
    }

    /**
     * @return list<array{label: string, detail: string, priority: string}>
     */
    protected function buildInsights(Collection $timeline, $alerts): array
    {
        $insights = [];

        if ($alerts->where('is_read', false)->count() > 0) {
            $insights[] = [
                'label' => 'Alertas não lidos',
                'detail' => $alerts->where('is_read', false)->count().' alerta(s) financeiro(s) aguardando revisão.',
                'priority' => 'high',
            ];
        }

        $telegram = $timeline->where('category', 'telegram')->count();
        if ($telegram > 0) {
            $insights[] = [
                'label' => 'Integração Telegram',
                'detail' => "{$telegram} ocorrência(s) nos logs — confira destino (/start + ID numérico).",
                'priority' => 'high',
            ];
        }

        $whatsapp = $timeline->where('category', 'whatsapp')->count();
        if ($whatsapp > 0) {
            $insights[] = [
                'label' => 'Integração WhatsApp',
                'detail' => "{$whatsapp} falha(s) de envio — valide API e número no .env.",
                'priority' => 'high',
            ];
        }

        $ai = $timeline->where('category', 'ia')->count();
        if ($ai > 0) {
            $insights[] = [
                'label' => 'Inteligência artificial',
                'detail' => "{$ai} evento(s) relacionados à IA — verifique API keys.",
                'priority' => 'high',
            ];
        }

        $db = $timeline->where('category', 'banco')->count();
        if ($db > 0) {
            $insights[] = [
                'label' => 'Banco de dados',
                'detail' => 'Erros SQL detectados — prioridade máxima.',
                'priority' => 'critical',
            ];
        }

        if ($insights === []) {
            $insights[] = [
                'label' => 'Nenhum problema crítico detectado',
                'detail' => 'Período analisado sem padrões graves nos alertas e logs filtrados.',
                'priority' => 'low',
            ];
        }

        return $insights;
    }
}
