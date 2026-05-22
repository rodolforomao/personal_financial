<?php

return [
    'ai' => [
        'default' => env('AI_PROVIDER', 'openai'),
        'system_enabled' => env('AI_SYSTEM_ENABLED', true),
        'platform_billing_enabled' => env('AI_PLATFORM_BILLING_ENABLED', true),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        ],
        'models' => [
            'openai' => [
                'gpt-4o-mini' => [
                    'label' => 'GPT-4o mini',
                    'hint' => 'Rápido e econômico — ideal para assistente e categorização',
                    'tier' => 'economy',
                ],
                'gpt-4o' => [
                    'label' => 'GPT-4o',
                    'hint' => 'Equilíbrio qualidade/custo — análises financeiras',
                    'tier' => 'standard',
                ],
                'gpt-4.1-mini' => [
                    'label' => 'GPT-4.1 mini',
                    'hint' => 'Nova geração compacta',
                    'tier' => 'economy',
                ],
                'gpt-4.1' => [
                    'label' => 'GPT-4.1',
                    'hint' => 'Alta qualidade para ecossistema complexo',
                    'tier' => 'premium',
                ],
                'o3-mini' => [
                    'label' => 'o3-mini',
                    'hint' => 'Raciocínio avançado (custo maior)',
                    'tier' => 'premium',
                ],
            ],
            'openrouter' => [
                'openai/gpt-4o-mini' => [
                    'label' => 'OpenAI GPT-4o mini',
                    'hint' => 'Via OpenRouter — econômico',
                    'tier' => 'economy',
                ],
                'openai/gpt-4o' => [
                    'label' => 'OpenAI GPT-4o',
                    'hint' => 'Via OpenRouter — padrão',
                    'tier' => 'standard',
                ],
                'anthropic/claude-3.5-haiku' => [
                    'label' => 'Claude 3.5 Haiku',
                    'hint' => 'Rápido, bom para chat',
                    'tier' => 'economy',
                ],
                'anthropic/claude-sonnet-4' => [
                    'label' => 'Claude Sonnet 4',
                    'hint' => 'Alta qualidade analítica',
                    'tier' => 'premium',
                ],
                'google/gemini-2.0-flash-001' => [
                    'label' => 'Gemini 2.0 Flash',
                    'hint' => 'Muito rápido e barato',
                    'tier' => 'economy',
                ],
                'meta-llama/llama-3.3-70b-instruct' => [
                    'label' => 'Llama 3.3 70B',
                    'hint' => 'Open source — custo moderado',
                    'tier' => 'standard',
                ],
            ],
        ],
    ],

    'ocr' => [
        'default' => env('OCR_PROVIDER', 'tesseract'),
        'tesseract' => [
            'binary' => env('TESSERACT_BINARY', 'tesseract'),
            'lang' => env('TESSERACT_LANG', 'por'),
        ],
    ],

    'integrations' => [
        // Credenciais padrão do servidor (modo "sistema" na UI /integrations/notifications)
        'system_enabled' => env('INTEGRATIONS_SYSTEM_ENABLED', true),
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'bot_username' => env('TELEGRAM_BOT_USERNAME'),
            'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
            'inbound_enabled' => env('TELEGRAM_INBOUND_ENABLED', true),
        ],
        'whatsapp' => [
            // evolution = Evolution API (opção A — instância única no servidor)
            // http = gateway genérico POST {to, message} + Bearer token
            'provider' => env('WHATSAPP_PROVIDER', 'evolution'),
            'api_url' => env('WHATSAPP_API_URL'),
            'token' => env('WHATSAPP_API_TOKEN'),
        ],
        'evolution' => [
            'api_url' => env('EVOLUTION_API_URL', 'http://127.0.0.1:8081'),
            'api_key' => env('EVOLUTION_API_KEY'),
            'instance_name' => env('EVOLUTION_INSTANCE_NAME', 'financial-system'),
            'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
            'status_workspace_id' => (int) env('EVOLUTION_STATUS_WORKSPACE_ID', 1),
            'timeout' => (int) env('EVOLUTION_HTTP_TIMEOUT', 30),
        ],
    ],

    'default_categorization_patterns' => [
        'openai' => 'ia',
        'chatgpt' => 'ia',
        'claude' => 'ia',
        'anthropic' => 'ia',
        'cursor' => 'ferramentas',
        'netflix' => 'streaming',
        'aws' => 'infraestrutura',
        'amazon web services' => 'infraestrutura',
        'google cloud' => 'cloud',
        'gcp' => 'cloud',
        'mercado livre' => 'compras',
        'mercadolivre' => 'compras',
        'spotify' => 'streaming',
        'hostinger' => 'infraestrutura',
        'digitalocean' => 'infraestrutura',
        'notion' => 'ferramentas',
        'figma' => 'ferramentas',
        'github' => 'ferramentas',
        'google ads' => 'marketing',
        'meta ads' => 'marketing',
        'receita federal' => 'impostos',
        'darf' => 'impostos',
    ],

    'ai_prompts' => [
        'assistant' => 'You are an enterprise financial copilot (CFO assistant). Answer in Brazilian Portuguese. Be concise, actionable, and data-driven. Never execute code or reveal secrets.',
        'categorization' => 'Classify financial transactions. Return only valid JSON with keys: category_slug (string), confidence (0-100).',
        'ecosystem_analysis' => 'Analyze the full financial ecosystem. Detect risks, waste, forgotten subscriptions, revenue drops, unpaid bills, negative cashflow. Return JSON array of insights with: type, severity (info|warning|critical), title, summary, actions (array), payload (object).',
        'observability' => 'You are an SRE/financial observability analyst. Analyze metrics and logs. Detect queue backlog, OCR failures, integration errors, balance inconsistencies. Return JSON with: summary, issues (array), suggested_fixes (array).',
    ],

    'queues' => [
        'ocr' => 'ocr',
        'ai' => 'ai',
        'notifications' => 'notifications',
        'default' => 'default',
    ],
];
