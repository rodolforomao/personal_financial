<?php

namespace App\Core\Support;

use Illuminate\Support\Str;

class LogInterpreter
{
    /**
     * @param  array<string, mixed>  $entry
     * @return array{category: string, title: string, hint: string, priority: string}
     */
    public function interpretLogEntry(array $entry): array
    {
        $message = Str::lower($entry['message'] ?? '');
        $level = strtoupper($entry['level'] ?? 'INFO');
        $context = $entry['context'] ?? [];

        foreach ($this->rules() as $rule) {
            if ($this->matches($message, $context, $rule['patterns'])) {
                return [
                    'category' => $rule['category'],
                    'title' => $rule['title'],
                    'hint' => $rule['hint'],
                    'priority' => $rule['priority'] ?? $this->priorityFromLevel($level),
                ];
            }
        }

        return [
            'category' => 'geral',
            'title' => $this->titleFromLevel($level),
            'hint' => $this->defaultHint($level),
            'priority' => $this->priorityFromLevel($level),
        ];
    }

    /**
     * @param  \Modules\Alerts\Infrastructure\Models\Alert  $alert
     * @return array{category: string, title: string, hint: string, priority: string}
     */
    public function interpretAlert($alert): array
    {
        $type = Str::lower($alert->type ?? '');
        $message = Str::lower($alert->message ?? '');

        $rules = [
            'cash_flow' => ['category' => 'financeiro', 'title' => 'Fluxo de caixa', 'hint' => 'Revise entradas/saídas previstas e saldo das contas.', 'priority' => 'high'],
            'subscription' => ['category' => 'assinatura', 'title' => 'Assinatura recorrente', 'hint' => 'Confira recorrências e próximos débitos automáticos.', 'priority' => 'medium'],
            'anomaly' => ['category' => 'anomalia', 'title' => 'Anomalia detectada', 'hint' => 'Valide transações recentes e categorização.', 'priority' => 'high'],
        ];

        foreach ($rules as $pattern => $meta) {
            if (str_contains($type, $pattern) || str_contains($message, $pattern)) {
                return $meta;
            }
        }

        $severity = $alert->severity->value ?? 'warning';

        return [
            'category' => 'alerta_plataforma',
            'title' => $alert->title,
            'hint' => 'Alerta gerado pelo motor de regras do workspace. '.$alert->message,
            'priority' => $severity === 'critical' ? 'critical' : ($severity === 'warning' ? 'high' : 'medium'),
        ];
    }

    protected function rules(): array
    {
        return [
            [
                'patterns' => ['telegram send failed', 'chat not found'],
                'category' => 'telegram',
                'title' => 'Falha ao enviar Telegram',
                'hint' => 'Envie /start ao bot, use o ID numérico do @userinfobot (não só @usuario) e teste em Integrações → Telegram.',
                'priority' => 'high',
            ],
            [
                'patterns' => ['telegram bot token not configured'],
                'category' => 'telegram',
                'title' => 'Telegram sem token',
                'hint' => 'Configure TELEGRAM_BOT_TOKEN no .env ou token próprio em Integrações.',
                'priority' => 'high',
            ],
            [
                'patterns' => ['whatsapp send failed', 'whatsapp integration not configured'],
                'category' => 'whatsapp',
                'title' => 'Falha no WhatsApp',
                'hint' => 'Verifique Evolution (EVOLUTION_* no .env, instância conectada) ou WHATSAPP_API_URL/token e número com DDI em Integrações.',
                'priority' => 'high',
            ],
            [
                'patterns' => ['ai_not_configured', 'openai', '401', 'invalid key', 'ai unavailable'],
                'category' => 'ia',
                'title' => 'Problema na IA',
                'hint' => 'Confira OPENAI_API_KEY no .env ou API key em Inteligência → Configuração IA.',
                'priority' => 'high',
            ],
            [
                'patterns' => ['sqlstate', 'queryexception', 'deadlock'],
                'category' => 'banco',
                'title' => 'Erro de banco de dados',
                'hint' => 'Verifique conexão MySQL, migrações pendentes e integridade dos dados.',
                'priority' => 'critical',
            ],
            [
                'patterns' => ['methodnotallowed', '405'],
                'category' => 'http',
                'title' => 'Método HTTP incorreto',
                'hint' => 'Rota chamada com verb errado (GET vs POST). Revise formulários e rotas em routes/web.php.',
                'priority' => 'medium',
            ],
            [
                'patterns' => ['horizon', 'queue', 'failed job'],
                'category' => 'fila',
                'title' => 'Fila / jobs',
                'hint' => 'Execute queue:work ou Horizon; veja tabela failed_jobs.',
                'priority' => 'high',
            ],
            [
                'patterns' => ['connection refused', 'timed out', 'could not resolve host'],
                'category' => 'rede',
                'title' => 'Falha de rede',
                'hint' => 'Serviço externo indisponível ou firewall bloqueando saída HTTP.',
                'priority' => 'high',
            ],
        ];
    }

    protected function matches(string $message, array $context, array $patterns): bool
    {
        $blob = $message.' '.json_encode($context);

        foreach ($patterns as $pattern) {
            if (str_contains($blob, Str::lower($pattern))) {
                return true;
            }
        }

        return false;
    }

    protected function priorityFromLevel(string $level): string
    {
        return match ($level) {
            'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => 'critical',
            'WARNING' => 'high',
            'NOTICE', 'INFO' => 'medium',
            default => 'low',
        };
    }

    protected function titleFromLevel(string $level): string
    {
        return match ($level) {
            'ERROR', 'CRITICAL' => 'Erro da aplicação',
            'WARNING' => 'Aviso no log',
            default => 'Registro informativo',
        };
    }

    protected function defaultHint(string $level): string
    {
        return match ($level) {
            'ERROR', 'CRITICAL' => 'Investigue stack trace abaixo e reproduza o fluxo que gerou o erro.',
            'WARNING' => 'Pode indicar integração degradada; monitore se repetir.',
            default => 'Evento registrado para auditoria.',
        };
    }
}
