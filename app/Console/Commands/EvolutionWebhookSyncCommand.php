<?php

namespace App\Console\Commands;

use App\Application\Services\PlatformOperationsGuide;
use Illuminate\Console\Command;
use Modules\Integrations\Application\Services\EvolutionService;

class EvolutionWebhookSyncCommand extends Command
{
    protected $signature = 'evolution:webhook-sync
                            {--url= : URL pública do webhook (padrão: APP_URL/api/v1/webhooks/whatsapp)}';

    protected $description = 'Registra na Evolution API o webhook do Laravel para eventos de conexão e mensagens';

    public function handle(EvolutionService $evolution): int
    {
        if (! $evolution->configured()) {
            $this->error('Evolution não configurada. Defina EVOLUTION_API_URL, EVOLUTION_API_KEY e EVOLUTION_INSTANCE_NAME no .env');

            return self::FAILURE;
        }

        $baseUrl = config('financial.integrations.evolution.api_url');
        $this->line("Evolution API: {$baseUrl}");

        try {
            $response = \Illuminate\Support\Facades\Http::baseUrl(rtrim((string) $baseUrl, '/'))
                ->withHeaders(['apikey' => config('financial.integrations.evolution.api_key')])
                ->timeout(5)
                ->get('/');
        } catch (\Throwable $e) {
            $this->error("Não foi possível conectar em {$baseUrl}: {$e->getMessage()}");
            $this->line('Verifique: docker compose ps evolution-api (deve estar Up, não Restarting).');

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error("Evolution respondeu HTTP {$response->status()} em {$baseUrl}");

            return self::FAILURE;
        }

        $instance = config('financial.integrations.evolution.instance_name');
        if (! $evolution->instanceExists()) {
            $this->warn("A instância \"{$instance}\" ainda não existe na Evolution.");
            $this->line('1. Abra o manager: http://127.0.0.1:8081/manager');
            $this->line("2. Crie a instância com o nome exato: {$instance}");
            $this->line('3. Escaneie o QR Code e aguarde status conectado');
            $this->line('4. Execute novamente: php artisan evolution:webhook-sync');

            return self::FAILURE;
        }

        $url = $this->option('url') ?? $this->resolveWebhookUrl();

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            $this->warn('A URL do webhook usa localhost — o container Evolution NÃO consegue alcançar isso.');
            $this->line('Defina no .env: EVOLUTION_WEBHOOK_PUBLIC_URL=http://host.docker.internal:8000/api/v1/webhooks/whatsapp');
            $this->line('(Linux Docker com extra_hosts host-gateway — já está no docker-compose.yml)');
        }

        if (! $evolution->setWebhook($url)) {
            $this->error('Falha ao registrar webhook na Evolution API (instância existe mas a API recusou).');

            return self::FAILURE;
        }

        $this->info("Webhook registrado: {$url}");
        $this->line('Envie o header apikey com EVOLUTION_API_KEY ou EVOLUTION_WEBHOOK_SECRET nas requisições da Evolution.');
        $this->newLine();
        $this->line(app(PlatformOperationsGuide::class)->consoleAfterWebhook('whatsapp'));

        return self::SUCCESS;
    }

    protected function resolveWebhookUrl(): string
    {
        $configured = config('financial.integrations.evolution.webhook_public_url');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim((string) config('app.url'), '/').'/api/v1/webhooks/whatsapp';
    }
}
