<?php

return [
    'app_name' => env('APP_NAME', 'Financial Intelligence'),
    'logo' => '<b>Financial</b>IQ',

    // Fallback se a tabela navigation_menu_items estiver vazia (ordem oficial: NavigationMenuSeeder).
    'menu' => [
        ['header' => 'PRINCIPAL'],
        [
            'text' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'bi bi-speedometer2',
        ],
        ['header' => 'FINANÇAS'],
        [
            'text' => 'Transações',
            'route' => 'transactions.index',
            'icon' => 'bi bi-arrow-left-right',
        ],
        [
            'text' => 'Categorias',
            'route' => 'categories.index',
            'icon' => 'bi bi-tags',
        ],
        [
            'text' => 'Auto categorização',
            'route' => 'categorization-rules.index',
            'icon' => 'bi bi-magic',
        ],
        [
            'text' => 'Empresas',
            'route' => 'companies.index',
            'icon' => 'bi bi-building',
        ],
        [
            'text' => 'Operações',
            'route' => 'operations.index',
            'icon' => 'bi bi-diagram-3',
        ],
        [
            'text' => 'Saneamento',
            'route' => 'data-hygiene.index',
            'icon' => 'bi bi-clipboard-check',
        ],
        [
            'text' => 'Salário CLT',
            'route' => 'clt-salaries.index',
            'icon' => 'bi bi-briefcase',
        ],
        [
            'text' => 'Projetos',
            'route' => 'projects.index',
            'icon' => 'bi bi-kanban',
        ],
        [
            'text' => 'Importar extrato',
            'route' => 'statements.index',
            'icon' => 'bi bi-bank2',
        ],
        [
            'text' => 'Patrimônio',
            'route' => 'assets.index',
            'icon' => 'bi bi-piggy-bank',
        ],
        [
            'text' => 'Documentos / OCR',
            'route' => 'documents.index',
            'icon' => 'bi bi-file-earmark-pdf',
        ],
        ['header' => 'INTELIGÊNCIA'],
        [
            'text' => 'Insights IA',
            'route' => 'ai.insights',
            'icon' => 'bi bi-lightbulb',
        ],
        [
            'text' => 'Assistente IA',
            'route' => 'ai.assistant',
            'icon' => 'bi bi-robot',
        ],
        [
            'text' => 'Configuração IA',
            'route' => 'ai.settings',
            'icon' => 'bi bi-key',
        ],
        ['header' => 'OPERAÇÃO'],
        [
            'text' => 'Alertas',
            'route' => 'alerts.index',
            'icon' => 'bi bi-bell',
        ],
        [
            'text' => 'Diagnóstico',
            'route' => 'observability.index',
            'icon' => 'bi bi-activity',
        ],
        [
            'text' => 'Integrações',
            'route' => 'integrations.settings',
            'icon' => 'bi bi-chat-dots',
        ],
        ['header' => 'ADMINISTRAÇÃO', 'role' => 'admin'],
        [
            'text' => 'Usuários (IA interna)',
            'route' => 'admin.users.index',
            'icon' => 'bi bi-people',
            'role' => 'admin',
        ],
    ],
];
