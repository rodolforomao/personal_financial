<?php

namespace Database\Seeders;

use App\Models\NavigationMenuItem;
use Illuminate\Database\Seeder;

class NavigationMenuSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ── PRINCIPAL — uso diário ──────────────────────────────────────
            ['type' => 'header', 'sort_order' => 10,  'label' => 'PRINCIPAL'],
            ['type' => 'link',   'sort_order' => 20,  'label' => 'Dashboard',            'route' => 'dashboard',                'icon' => 'bi bi-speedometer2'],
            ['type' => 'link',   'sort_order' => 30,  'label' => 'Transações',            'route' => 'transactions.index',       'icon' => 'bi bi-arrow-left-right'],
            ['type' => 'link',   'sort_order' => 40,  'label' => 'Receitas recorrentes',  'route' => 'recurring-income.index',   'icon' => 'bi bi-arrow-repeat'],
            ['type' => 'link',   'sort_order' => 50,  'label' => 'Receitas unitárias',    'route' => 'income.index',             'icon' => 'bi bi-cash-stack'],
            ['type' => 'link',   'sort_order' => 60,  'label' => 'Importar extrato',      'route' => 'statements.index',         'icon' => 'bi bi-bank2'],
            ['type' => 'link',   'sort_order' => 70,  'label' => 'Resumo financeiro',     'route' => 'reports.index',            'icon' => 'bi bi-bar-chart-line'],

            // ── NEGÓCIOS — entidades e estrutura ───────────────────────────
            ['type' => 'header', 'sort_order' => 100, 'label' => 'NEGÓCIOS'],
            ['type' => 'link',   'sort_order' => 110, 'label' => 'Operações',             'route' => 'operations.index',         'icon' => 'bi bi-diagram-3'],
            ['type' => 'link',   'sort_order' => 120, 'label' => 'Empresas',              'route' => 'companies.index',          'icon' => 'bi bi-building'],
            ['type' => 'link',   'sort_order' => 130, 'label' => 'Projetos',              'route' => 'projects.index',           'icon' => 'bi bi-kanban'],
            ['type' => 'link',   'sort_order' => 140, 'label' => 'Patrimônio',            'route' => 'assets.index',             'icon' => 'bi bi-piggy-bank'],
            ['type' => 'link',   'sort_order' => 150, 'label' => 'Salário CLT',           'route' => 'clt-salaries.index',       'icon' => 'bi bi-briefcase'],

            // ── CONFIGURAÇÃO — setup eventual ──────────────────────────────
            ['type' => 'header', 'sort_order' => 200, 'label' => 'CONFIGURAÇÃO'],
            ['type' => 'link',   'sort_order' => 210, 'label' => 'Categorias',            'route' => 'categories.index',         'icon' => 'bi bi-tags'],
            ['type' => 'link',   'sort_order' => 220, 'label' => 'Auto categorização',    'route' => 'categorization-rules.index','icon' => 'bi bi-magic'],
            ['type' => 'link',   'sort_order' => 230, 'label' => 'Documentos / OCR',      'route' => 'documents.index',          'icon' => 'bi bi-file-earmark-pdf'],
            ['type' => 'link',   'sort_order' => 240, 'label' => 'Saneamento',            'route' => 'data-hygiene.index',       'icon' => 'bi bi-clipboard-check'],

            // ── INTELIGÊNCIA ───────────────────────────────────────────────
            ['type' => 'header', 'sort_order' => 300, 'label' => 'INTELIGÊNCIA'],
            ['type' => 'link',   'sort_order' => 310, 'label' => 'Assistente IA',         'route' => 'ai.assistant',             'icon' => 'bi bi-robot'],
            ['type' => 'link',   'sort_order' => 320, 'label' => 'Insights IA',           'route' => 'ai.insights',              'icon' => 'bi bi-lightbulb'],
            ['type' => 'link',   'sort_order' => 330, 'label' => 'Configuração IA',       'route' => 'ai.settings',              'icon' => 'bi bi-key'],

            // ── SISTEMA — operação e conta ─────────────────────────────────
            ['type' => 'header', 'sort_order' => 400, 'label' => 'SISTEMA'],
            ['type' => 'link',   'sort_order' => 410, 'label' => 'Alertas',               'route' => 'alerts.index',             'icon' => 'bi bi-bell'],
            ['type' => 'link',   'sort_order' => 420, 'label' => 'Telegram / WhatsApp',   'route' => 'integrations.settings',    'icon' => 'bi bi-chat-dots'],
            ['type' => 'link',   'sort_order' => 430, 'label' => 'Workspaces',            'route' => 'workspace.index',          'icon' => 'bi bi-building-check'],
            ['type' => 'link',   'sort_order' => 440, 'label' => 'Conta e segurança',     'route' => 'account.security',         'icon' => 'bi bi-shield-lock'],
            ['type' => 'link',   'sort_order' => 450, 'label' => 'Diagnóstico',           'route' => 'observability.index',      'icon' => 'bi bi-activity'],

            // ── ADMINISTRAÇÃO — restrito ────────────────────────────────────
            ['type' => 'header', 'sort_order' => 500, 'label' => 'ADMINISTRAÇÃO', 'required_role' => 'admin'],
            ['type' => 'link',   'sort_order' => 510, 'label' => 'Usuários e planos',     'route' => 'admin.users.index',        'icon' => 'bi bi-people',  'required_role' => 'admin'],
            ['type' => 'link',   'sort_order' => 520, 'label' => 'Configurações',         'route' => 'admin.settings.edit',      'icon' => 'bi bi-gear',    'required_role' => 'admin'],
        ];

        $expectedRoutes  = [];
        $expectedHeaders = [];

        foreach ($items as $item) {
            $key = $item['type'] === 'header'
                ? ['type' => 'header', 'label' => $item['label']]
                : ['type' => 'link',   'route' => $item['route']];

            NavigationMenuItem::query()->updateOrCreate(
                $key,
                [
                    'sort_order'    => $item['sort_order'],
                    'label'         => $item['label'],
                    'icon'          => $item['icon'] ?? null,
                    'required_role' => $item['required_role'] ?? null,
                    'is_active'     => true,
                ]
            );

            if ($item['type'] === 'link') {
                $expectedRoutes[] = $item['route'];
            } else {
                $expectedHeaders[] = $item['label'];
            }
        }

        // Remove itens que não estão mais na lista
        NavigationMenuItem::query()
            ->where('type', 'link')
            ->whereNotIn('route', $expectedRoutes)
            ->delete();

        NavigationMenuItem::query()
            ->where('type', 'header')
            ->whereNotIn('label', $expectedHeaders)
            ->delete();
    }
}
