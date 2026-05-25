<?php

return [
    'http' => [
        'verify_ssl' => env('HTTP_VERIFY_SSL', true),
        'ca_bundle' => env('CURL_CA_BUNDLE'),
        'force_ipv4' => env('HTTP_FORCE_IPV4', true),
    ],

    'security' => [
        'require_password_for_transaction_sensitive_edit' => env('FINANCIAL_REQUIRE_PASSWORD_ON_EDIT', true),
    ],

    'billing' => [
        'liquidx' => [
            'base_url' => rtrim(env('LIQUIDX_API_BASE_URL', 'https://liquidx.pro/api'), '/'),
            'api_key' => env('LIQUIDX_API_KEY'),
            'integrated_code' => env('LIQUIDX_INTEGRATED_CODE'),
            'integrated_payment_path' => env('LIQUIDX_INTEGRATED_PAYMENT_PATH', '/integrated-payment'),
            'integrated_payment_status_path' => env('LIQUIDX_INTEGRATED_PAYMENT_STATUS_PATH', '/integrated-payment/status'),
            'thirdwallet' => env('LIQUIDX_THIRDWALLET'),
            'webhook_secret' => env('LIQUIDX_WEBHOOK_SECRET'),
            'timeout' => (int) env('LIQUIDX_HTTP_TIMEOUT', 20),
            'default_payer_phone' => env('LIQUIDX_DEFAULT_PAYER_PHONE'),
        ],
    ],

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
        'pdf' => [
            'pdftoppm_binary' => env('PDFTOPPM_BINARY', 'pdftoppm'),
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
            // true = processa na hora no webhook (resposta imediata). false = fila (exige queue:work).
            'inbound_sync' => env('TELEGRAM_INBOUND_SYNC', true),
            'download_retries' => (int) env('TELEGRAM_DOWNLOAD_RETRIES', 3),
            'download_retry_delay_ms' => (int) env('TELEGRAM_DOWNLOAD_RETRY_DELAY_MS', 800),
            'poll_timeout' => (int) env('TELEGRAM_POLL_TIMEOUT', 25),
            // Cron: php artisan schedule:work — dispensa terminal com telegram:poll
            'scheduled_poll' => env('TELEGRAM_SCHEDULED_POLL', false),
            'scheduled_queue' => env('TELEGRAM_SCHEDULED_QUEUE', false),
            'queue_names' => env('TELEGRAM_QUEUE_NAMES', 'notifications,default'),
            // Chat IDs (numéricos) com permissão para /fila e /run <admin>
            'admin_chat_ids' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('TELEGRAM_ADMIN_CHAT_IDS', '')),
            ))),
            'allowed_artisan_commands' => [
                'financial:daily',
                'financial:scan-alerts',
                'financial:normalize-workspace',
                'queue:work',
                'telegram:poll',
                'telegram:webhook-sync',
                'evolution:webhook-sync',
            ],
            'background_commands' => [
                'daily' => [
                    'artisan' => 'financial:daily',
                    'label' => 'financial:daily',
                    'description' => 'Inteligência financeira diária',
                    'public' => false,
                ],
                'alerts' => [
                    'artisan' => 'financial:scan-alerts',
                    'label' => 'financial:scan-alerts',
                    'description' => 'Varredura de alertas',
                    'public' => false,
                ],
                'webhook' => [
                    'artisan' => 'telegram:webhook-sync',
                    'label' => 'telegram:webhook-sync',
                    'description' => 'Registra webhook HTTPS Telegram',
                    'public' => false,
                ],
                'evolution' => [
                    'artisan' => 'evolution:webhook-sync',
                    'label' => 'evolution:webhook-sync',
                    'description' => 'Registra webhook WhatsApp (Evolution)',
                    'public' => false,
                ],
                'poll' => [
                    'artisan' => 'telegram:poll',
                    'label' => 'telegram:poll --once',
                    'description' => 'Um ciclo de poll (alias de /poll)',
                    'options' => ['--once' => true],
                    'public' => false,
                ],
            ],
        ],
        'whatsapp' => [
            // evolution = Evolution API (opção A — instância única no servidor)
            // http = gateway genérico POST {to, message} + Bearer token
            'provider' => env('WHATSAPP_PROVIDER', 'evolution'),
            'api_url' => env('WHATSAPP_API_URL'),
            'token' => env('WHATSAPP_API_TOKEN'),
            'inbound_enabled' => env('WHATSAPP_INBOUND_ENABLED', true),
            // true = processa comprovante na hora no webhook (sem queue:work)
            'inbound_sync' => env('WHATSAPP_INBOUND_SYNC', true),
        ],
        'evolution' => [
            'api_url' => env('EVOLUTION_API_URL', 'http://127.0.0.1:8081'),
            'api_key' => env('EVOLUTION_API_KEY'),
            'instance_name' => env('EVOLUTION_INSTANCE_NAME', 'financial-system'),
            'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
            // URL que o container Evolution usa para chamar o Laravel (não use localhost se Evolution está no Docker)
            'webhook_public_url' => env('EVOLUTION_WEBHOOK_PUBLIC_URL'),
            'status_workspace_id' => (int) env('EVOLUTION_STATUS_WORKSPACE_ID', 1),
            'timeout' => (int) env('EVOLUTION_HTTP_TIMEOUT', 30),
            // Envia imagens/PDF em base64 no webhook (comprovantes WhatsApp)
            'webhook_base64' => env('EVOLUTION_WEBHOOK_BASE64', true),
        ],
        'gmail' => [
            'client_id' => env('GMAIL_CLIENT_ID'),
            'client_secret' => env('GMAIL_CLIENT_SECRET'),
            'redirect_uri' => env('GMAIL_REDIRECT_URI'),
            'scheduled_sync' => env('GMAIL_SCHEDULED_SYNC', false),
            'sync_limit' => (int) env('GMAIL_SYNC_LIMIT', 25),
            'search_query' => env(
                'GMAIL_SEARCH_QUERY',
                'newer_than:30d (compra OR gasto OR despesa OR pagamento OR pix OR recebido OR recebimento OR boleto OR fatura OR "nota fiscal" OR nf-e)'
            ),
        ],
    ],

    'statement_import' => [
        'bank_signatures' => [
            'inter' => ['banco inter', '<org>banco inter', 'bankid>077', 'bankid>00000077', 'fid>077'],
            'nubank' => ['nubank', 'nu pagamentos', 'bankid>260'],
        ],
        'merchant_patterns' => [
            'uber' => ['uber', 'uberrides', 'uber *trip', 'uber*trip'],
        ],
        'netted_pair' => [
            // Diferença máxima entre compra e estorno (ou entre dois estornos) no mesmo dia — Uber/Inter
            'max_amount_diff' => (float) env('STATEMENT_NETTED_MAX_AMOUNT_DIFF', 1.0),
            'purchase_patterns' => [
                'compra no debito',
                'compra no débito',
                'compra cartão',
                'compra cartao',
            ],
            'estorno_patterns' => [
                'estorno no estabelecimento',
                'estorno:',
            ],
        ],
        'payment_method_patterns' => [
            'pix enviado' => 'pix',
            'pix recebido' => 'pix',
            'compra no debito' => 'card',
            'compra no débito' => 'card',
            'compra cartão' => 'card',
            'compra cartao' => 'card',
            'estorno no estabelecimento' => 'card',
            'estorno:' => 'card',
        ],
    ],

    /*
    | Legenda do comprovante (Telegram/WhatsApp): "Airbnb, residencial oliveiras, nubank, pix"
    | Tokens separados por vírgula; palavras-chave mapeiam categoria/empresa/operação no cadastro.
    */
    'receipt_caption' => [
        'confirmed_on_inbound' => env('RECEIPT_CONFIRMED_ON_INBOUND', true),
        'category_keywords' => [
            'airbnb' => 'aluguel-airbnb',
            'aluguel airbnb' => 'aluguel-airbnb',
            'compra de tenis' => 'compras',
            'compra de tênis' => 'compras',
            'tenis' => 'compras',
            'tênis' => 'compras',
            'adidas' => 'compras',
        ],
        'company_aliases' => [
            'residencial oliveiras' => 'Residencial Oliveiras',
        ],
        'operation_aliases' => [
            'residencial oliveiras' => 'residencial-oliveiras',
        ],
        'ignore_tokens' => [
            'nubank', 'pix', 'inter', 'itau', 'bradesco', 'santander', 'caixa',
            'banco do brasil', 'bb', 'c6', 'stone', 'picpay', 'mercado pago',
            'ted', 'doc', 'boleto', 'cartão', 'cartao', 'débito', 'debito', 'crédito', 'credito',
        ],
    ],

    'default_categorization_patterns' => [
        // Ordem: padrões mais específicos primeiro
        'debito automatico' => 'contas-recorrentes',
        'débito automático' => 'contas-recorrentes',
        'debito autom' => 'contas-recorrentes',
        'pagamento automatico' => 'contas-recorrentes',
        'pagamento automático' => 'contas-recorrentes',
        'uber eats' => 'alimentacao',
        'ubereats' => 'alimentacao',
        'uber' => 'transporte',
        'uberrides' => 'transporte',
        '99 pop' => 'transporte',
        '99app' => 'transporte',
        '99 app' => 'transporte',
        'cabify' => 'transporte',
        'hotel' => 'viagem',
        'hospedagem' => 'viagem',
        'booking.com' => 'viagem',
        'decolar' => 'viagem',
        'airbnb' => 'viagem',
        'latam' => 'viagem',
        'gol linhas' => 'viagem',
        'azul linhas' => 'viagem',
        'ifood' => 'alimentacao',
        'rappi' => 'alimentacao',
        'restaurante' => 'alimentacao',
        'lanchonete' => 'alimentacao',
        'padaria' => 'alimentacao',
        'supermercado' => 'alimentacao',
        'carrefour' => 'alimentacao',
        'atacadao' => 'alimentacao',
        'farmacia' => 'saude',
        'farmácia' => 'saude',
        'drogaria' => 'saude',
        'drogasil' => 'saude',
        'posto' => 'combustivel',
        'shell box' => 'combustivel',
        'ipiranga' => 'combustivel',
        'energia eletrica' => 'utilidades',
        'energia elétrica' => 'utilidades',
        'cemig' => 'utilidades',
        'copasa' => 'utilidades',
        'sabesp' => 'utilidades',
        'vivo' => 'utilidades',
        'claro' => 'utilidades',
        'tim' => 'utilidades',
        'magazine luiza' => 'compras',
        'magalu' => 'compras',
        'shopee' => 'compras',
        'amazon' => 'compras',
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
        'evento b3' => 'dividendos',
        'salário' => 'salario-clt',
        'salario' => 'salario-clt',
        'clt' => 'salario-clt',
        'holerite' => 'salario-clt',
        'folha de pagamento' => 'salario-clt',
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
