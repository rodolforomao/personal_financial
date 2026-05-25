<?php

namespace App\Console\Commands;

use App\Core\Support\NotificationDestinationNormalizer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Integrations\Application\Services\EvolutionService;
use Modules\Integrations\Infrastructure\Models\WebhookLog;

class EvolutionDiagnoseCommand extends Command
{
    protected $signature = 'evolution:diagnose';

    protected $description = 'Diagnóstico WhatsApp/Evolution: conexão, webhook e números vinculados';

    public function handle(EvolutionService $evolution): int
    {
        $this->info('=== Diagnóstico Evolution / WhatsApp ===');

        if (! $evolution->configured()) {
            $this->error('Evolution não configurada no .env (EVOLUTION_API_URL, EVOLUTION_API_KEY, EVOLUTION_INSTANCE_NAME)');

            return self::FAILURE;
        }

        $state = $evolution->connectionState();
        $this->line('Instância: '.config('financial.integrations.evolution.instance_name'));
        $this->line('Status: '.($state['state'] ?? 'desconhecido'));

        if (($state['state'] ?? '') !== 'open') {
            $this->warn('WhatsApp não está open — escaneie o QR no manager.');
        }

        $webhookUrl = config('financial.integrations.evolution.webhook_public_url')
            ?: rtrim((string) config('app.url'), '/').'/api/v1/webhooks/whatsapp';
        $this->line('Webhook configurado (Laravel): '.$webhookUrl);

        if (str_contains($webhookUrl, 'localhost') || str_contains($webhookUrl, '127.0.0.1')) {
            $this->error('APP_URL localhost não funciona para Evolution no Docker.');
            $this->line('Corrija: EVOLUTION_WEBHOOK_PUBLIC_URL=http://host.docker.internal:8000/api/v1/webhooks/whatsapp');
            $this->line('Depois: php artisan evolution:webhook-sync');
        }

        $apiKey = config('financial.integrations.evolution.webhook_secret')
            ?: config('financial.integrations.evolution.api_key');

        $localProbe = rtrim((string) config('app.url'), '/').'/api/v1/webhooks/whatsapp';
        try {
            $probe = Http::timeout(5)->post($localProbe, [
                'event' => 'connection.update',
                'apikey' => $apiKey,
                'data' => ['state' => 'open'],
            ]);
            $this->line('Teste POST webhook (apikey no corpo, como Evolution): HTTP '.$probe->status());
            if ($probe->status() >= 400) {
                $this->warn('Resposta: '.$probe->body());
            }
        } catch (\Throwable $e) {
            $this->error('Laravel não responde em '.$localProbe.' — rode: php artisan serve');
        }

        if (str_contains($webhookUrl, 'host.docker.internal')) {
            $this->line('Evolution (Docker) precisa alcançar a porta 8000 no host.');
            $this->warn('Use: php artisan serve --host=0.0.0.0 --port=8000');
            $this->line('(Se o serve só escuta 127.0.0.1, o container recebe "Connection refused".)');
        }

        $logCount = WebhookLog::query()->where('provider', 'evolution_whatsapp')->count();
        $recent = WebhookLog::query()
            ->where('provider', 'evolution_whatsapp')
            ->orderByDesc('id')
            ->limit(3)
            ->get(['event', 'created_at']);

        $this->line("Webhook_logs (Evolution): {$logCount} total");
        if ($logCount === 0) {
            $this->warn('Nenhum evento chegou ao Laravel — o webhook na Evolution está errado ou inativo.');
        } else {
            foreach ($recent as $row) {
                $this->line("  • {$row->created_at} {$row->event}");
            }
        }

        $this->line('QUEUE_CONNECTION='.config('queue.default'));
        $this->line('WHATSAPP_INBOUND_SYNC='.(config('financial.integrations.whatsapp.inbound_sync') ? 'true' : 'false'));

        $this->newLine();
        $this->info('Números vinculados em /integrations/notifications:');
        $any = false;
        foreach (User::all() as $user) {
            $wa = NotificationDestinationNormalizer::whatsapp(
                (string) ($user->preferences['notifications']['whatsapp_phone'] ?? '')
            );
            if ($wa) {
                $any = true;
                $this->line("  {$user->email} → {$wa}");
            }
        }
        if (! $any) {
            $this->warn('Nenhum usuário com WhatsApp cadastrado.');
        }

        $this->newLine();
        $this->line('Envie o comprovante PARA o número conectado na instância (não para um número aleatório).');
        $this->line('Use conversa direta (1:1), não grupo WhatsApp — mensagens em @g.us são ignoradas.');
        $this->line('O remetente deve ser o mesmo número cadastrado no sistema.');

        return self::SUCCESS;
    }
}
