<?php

namespace Database\Seeders;

use App\Models\NavigationMenuItem;
use Illuminate\Database\Seeder;

class NavigationMenuSeeder extends Seeder
{
    /**
     * Ordem por importância e uso diário (menor sort_order = mais acima).
     */
    public function run(): void
    {
        $items = [
            // Principal
            ['type' => 'header', 'sort_order' => 10, 'label' => 'PRINCIPAL'],
            ['type' => 'link', 'sort_order' => 20, 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi bi-speedometer2'],

            // Finanças — fluxo diário primeiro
            ['type' => 'header', 'sort_order' => 100, 'label' => 'FINANÇAS'],
            ['type' => 'link', 'sort_order' => 110, 'label' => 'Transações', 'route' => 'transactions.index', 'icon' => 'bi bi-arrow-left-right'],
            ['type' => 'link', 'sort_order' => 120, 'label' => 'Importar extrato', 'route' => 'statements.index', 'icon' => 'bi bi-bank2'],
            ['type' => 'link', 'sort_order' => 130, 'label' => 'Documentos / OCR', 'route' => 'documents.index', 'icon' => 'bi bi-file-earmark-pdf'],
            ['type' => 'link', 'sort_order' => 140, 'label' => 'Categorias', 'route' => 'categories.index', 'icon' => 'bi bi-tags'],
            ['type' => 'link', 'sort_order' => 150, 'label' => 'Auto categorização', 'route' => 'categorization-rules.index', 'icon' => 'bi bi-magic'],
            ['type' => 'link', 'sort_order' => 160, 'label' => 'Empresas', 'route' => 'companies.index', 'icon' => 'bi bi-building'],
            ['type' => 'link', 'sort_order' => 170, 'label' => 'Operações', 'route' => 'operations.index', 'icon' => 'bi bi-diagram-3'],
            ['type' => 'link', 'sort_order' => 175, 'label' => 'Saneamento', 'route' => 'data-hygiene.index', 'icon' => 'bi bi-clipboard-check'],
            ['type' => 'link', 'sort_order' => 180, 'label' => 'Salário CLT', 'route' => 'clt-salaries.index', 'icon' => 'bi bi-briefcase'],
            ['type' => 'link', 'sort_order' => 190, 'label' => 'Patrimônio', 'route' => 'assets.index', 'icon' => 'bi bi-piggy-bank'],
            ['type' => 'link', 'sort_order' => 200, 'label' => 'Projetos', 'route' => 'projects.index', 'icon' => 'bi bi-kanban'],

            // Inteligência
            ['type' => 'header', 'sort_order' => 300, 'label' => 'INTELIGÊNCIA'],
            ['type' => 'link', 'sort_order' => 310, 'label' => 'Assistente IA', 'route' => 'ai.assistant', 'icon' => 'bi bi-robot'],
            ['type' => 'link', 'sort_order' => 320, 'label' => 'Insights IA', 'route' => 'ai.insights', 'icon' => 'bi bi-lightbulb'],
            ['type' => 'link', 'sort_order' => 330, 'label' => 'Configuração IA', 'route' => 'ai.settings', 'icon' => 'bi bi-key'],

            // Operação / integrações
            ['type' => 'header', 'sort_order' => 400, 'label' => 'OPERAÇÃO'],
            ['type' => 'link', 'sort_order' => 410, 'label' => 'Alertas', 'route' => 'alerts.index', 'icon' => 'bi bi-bell'],
            ['type' => 'link', 'sort_order' => 420, 'label' => 'Telegram / WhatsApp', 'route' => 'integrations.settings', 'icon' => 'bi bi-chat-dots'],
            ['type' => 'link', 'sort_order' => 430, 'label' => 'Diagnóstico', 'route' => 'observability.index', 'icon' => 'bi bi-activity'],

            // Admin
            ['type' => 'header', 'sort_order' => 500, 'label' => 'ADMINISTRAÇÃO', 'required_role' => 'admin'],
            ['type' => 'link', 'sort_order' => 510, 'label' => 'Usuários e planos', 'route' => 'admin.users.index', 'icon' => 'bi bi-people', 'required_role' => 'admin'],
            ['type' => 'link', 'sort_order' => 520, 'label' => 'Configurações', 'route' => 'admin.settings.edit', 'icon' => 'bi bi-gear', 'required_role' => 'admin'],
        ];

        foreach ($items as $item) {
            $key = $item['type'] === 'header'
                ? ['type' => 'header', 'label' => $item['label']]
                : ['type' => 'link', 'route' => $item['route']];

            NavigationMenuItem::query()->updateOrCreate(
                $key,
                [
                    'sort_order' => $item['sort_order'],
                    'label' => $item['label'],
                    'icon' => $item['icon'] ?? null,
                    'required_role' => $item['required_role'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
