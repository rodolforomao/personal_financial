<?php

return [
    'app_name' => env('APP_NAME', 'FinancialIQ'),
    'logo' => '<b>Financial</b><span>IQ</span>',

    // Fallback se a tabela navigation_menu_items estiver vazia (ordem oficial: NavigationMenuSeeder).
    'menu' => [
        // ── PRINCIPAL — uso diário ────────────────────────────────────────
        ['header' => 'PRINCIPAL'],
        ['text' => 'Dashboard',           'route' => 'dashboard',                 'icon' => 'bi bi-speedometer2'],
        ['text' => 'Transações',           'route' => 'transactions.index',        'icon' => 'bi bi-arrow-left-right'],
        ['text' => 'Receitas recorrentes', 'route' => 'recurring-income.index',    'icon' => 'bi bi-arrow-repeat'],
        ['text' => 'Receitas unitárias',   'route' => 'income.index',              'icon' => 'bi bi-cash-stack'],
        ['text' => 'Importar extrato',     'route' => 'statements.index',          'icon' => 'bi bi-bank2'],
        ['text' => 'Resumo financeiro',    'route' => 'reports.index',             'icon' => 'bi bi-bar-chart-line'],

        // ── NEGÓCIOS — entidades e estrutura ─────────────────────────────
        ['header' => 'NEGÓCIOS'],
        ['text' => 'Operações',            'route' => 'operations.index',          'icon' => 'bi bi-diagram-3'],
        ['text' => 'Empresas',             'route' => 'companies.index',           'icon' => 'bi bi-building'],
        ['text' => 'Projetos',             'route' => 'projects.index',            'icon' => 'bi bi-kanban'],
        ['text' => 'Patrimônio',           'route' => 'assets.index',              'icon' => 'bi bi-piggy-bank'],
        ['text' => 'Salário CLT',          'route' => 'clt-salaries.index',        'icon' => 'bi bi-briefcase'],

        // ── CONFIGURAÇÃO — setup eventual ─────────────────────────────────
        ['header' => 'CONFIGURAÇÃO'],
        ['text' => 'Categorias',           'route' => 'categories.index',          'icon' => 'bi bi-tags'],
        ['text' => 'Auto categorização',   'route' => 'categorization-rules.index','icon' => 'bi bi-magic'],
        ['text' => 'Documentos / OCR',     'route' => 'documents.index',           'icon' => 'bi bi-file-earmark-pdf'],
        ['text' => 'Saneamento',           'route' => 'data-hygiene.index',        'icon' => 'bi bi-clipboard-check'],

        // ── INTELIGÊNCIA ──────────────────────────────────────────────────
        ['header' => 'INTELIGÊNCIA'],
        ['text' => 'Assistente IA',        'route' => 'ai.assistant',              'icon' => 'bi bi-robot'],
        ['text' => 'Insights IA',          'route' => 'ai.insights',               'icon' => 'bi bi-lightbulb'],
        ['text' => 'Configuração IA',      'route' => 'ai.settings',               'icon' => 'bi bi-key'],

        // ── SISTEMA — operação e conta ────────────────────────────────────
        ['header' => 'SISTEMA'],
        ['text' => 'Alertas',              'route' => 'alerts.index',              'icon' => 'bi bi-bell'],
        ['text' => 'Telegram / WhatsApp',  'route' => 'integrations.settings',     'icon' => 'bi bi-chat-dots'],
        ['text' => 'Workspaces',           'route' => 'workspace.index',           'icon' => 'bi bi-building-check'],
        ['text' => 'Conta e segurança',    'route' => 'account.security',          'icon' => 'bi bi-shield-lock'],
        ['text' => 'Diagnóstico',          'route' => 'observability.index',       'icon' => 'bi bi-activity'],

        // ── ADMINISTRAÇÃO — restrito ──────────────────────────────────────
        ['header' => 'ADMINISTRAÇÃO', 'role' => 'admin'],
        ['text' => 'Usuários e planos',    'route' => 'admin.users.index',         'icon' => 'bi bi-people',  'role' => 'admin'],
        ['text' => 'Configurações',        'route' => 'admin.settings.edit',       'icon' => 'bi bi-gear',    'role' => 'admin'],
    ],
];
