# Financial Intelligence Platform

SaaS financeiro enterprise com Laravel, IA, OCR, alertas inteligentes e arquitetura modular.

## Stack

- **Backend:** Laravel 13
- **Banco:** MySQL 8
- **Cache/Filas:** Redis + Horizon
- **Auth:** Sanctum + RBAC (Spatie Permission) + 2FA
- **IA:** OpenAI / OpenRouter (desacoplado)
- **OCR:** Tesseract / Vision API

## Arquitetura

```
Modules/
├── Core/           # Workspaces, feature flags, multi-tenant
├── Finance/        # Transações, fluxo de caixa, previsões, patrimônio
├── Companies/      # Empresas, contratos, recorrências
├── Projects/       # Projetos, ROI, custos
├── Categorization/ # Regras + IA semântica
├── OCR/            # Documentos, fila OCR
├── Intelligence/   # IA financeira, observabilidade, assistente
├── Alerts/         # Detecção e notificações
└── Integrations/   # Telegram, WhatsApp, webhooks
```

Cada módulo segue camadas **Domain → Application → Infrastructure → Presentation**.

Documentação detalhada: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Roadmap: [docs/ROADMAP.md](docs/ROADMAP.md) | **Prompt inicial × código:** [docs/PROMPT_TRACEABILITY.md](docs/PROMPT_TRACEABILITY.md)

**Deploy (passo a passo):** [docs/DEPLOY.md](docs/DEPLOY.md) · **Dados e produção:** [docs/DADOS_E_PRODUCAO.md](docs/DADOS_E_PRODUCAO.md)

## Setup rápido (desenvolvimento)

### MySQL local (systemd — porta 3306) — recomendado

Se o MySQL já está ligado no seu gestor de BD:

```bash
composer install
cp .env.example .env
php artisan key:generate

# Cria DB + utilizador financial (pede password do root/admin)
bash scripts/setup-mysql-local.sh

./scripts/start.sh
# Para encerrar o ambiente deste projeto:
./scripts/stop.sh
# Atalhos equivalentes: composer start | composer stop
# Um único processo substitui poll + fila + rotina diária (com TELEGRAM_SCHEDULED_*=true no .env):
php artisan schedule:work
# Alternativa manual: php artisan queue:listen --queue=default,ocr,ai,notifications
```

Manualmente: `mysql -u root -p < scripts/mysql-setup.sql` e depois `php artisan migrate --seed`.

### Alternativa: MySQL Docker (porta 3307 — compartilhada)

Se não tiver acesso admin ao MySQL local:

```bash
bash scripts/setup-local.sh
# MySQL 3307 e Redis 6379/6380 são portas compartilhadas entre projetos;
# se já estiverem em uso, o script reutiliza o serviço existente (não sobe outro container).
```

**Login demo:** `admin@financial.local` / `password`

### Problemas comuns

| Erro | Solução |
|------|---------|
| `Access denied for user 'financial'` | Rode `bash scripts/setup-mysql-local.sh` (MySQL local) ou use Docker com `DB_PORT=3307` |
| `Access denied for user 'sa'` com `DB_USERNAME=root` no `.env` | O shell exporta `DB_USERNAME=sa` (SQL Server). Use `./scripts/artisan migrate` ou `env -u DB_USERNAME -u DB_PASSWORD php artisan ...` |
| `PHP Redis extension is not installed` | `.env`: `QUEUE_CONNECTION=database` e `CACHE_STORE=database` (já no `.env.example`) |
| `php artisan horizon` falha | Use `php artisan queue:listen` em desenvolvimento |

## API

Base: `/api/v1` — Header obrigatório: `X-Workspace-Id: 1`

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/auth/login` | Autenticação |
| GET | `/api/v1/dashboard` | Dashboard CFO |
| CRUD | `/api/v1/transactions` | Receitas/despesas |
| CRUD | `/api/v1/companies` | Empresas |
| CRUD | `/api/v1/projects` | Projetos |
| POST | `/api/v1/documents` | Upload OCR |
| GET | `/api/v1/alerts` | Alertas |
| POST | `/api/v1/ai/assistant` | Copiloto IA |
| POST | `/api/v1/ai/analyze` | Análise do ecossistema |
| GET/POST | `/api/v1/categories` | Categorias de gastos |
| GET/POST | `/api/v1/accounts` | Contas financeiras |
| GET | `/api/v1/recurring-items` | Itens recorrentes |
| POST | `/api/v1/imports/ofx` | Importar extrato OFX |
| POST | `/api/v1/imports/csv` | Importar CSV |

**Fase 1 (backend):** ver [docs/PHASE1.md](docs/PHASE1.md). Testes (MySQL `financial_test`):

```bash
bash scripts/setup-mysql-test.sh   # uma vez
./scripts/test.sh                  # preferir em vez de php artisan test
```

```bash
php artisan financial:daily          # rotina diária (scheduler 06:00)
php artisan financial:scan-alerts    # só alertas
```

## Deploy para produção

Checklist completo (backup, restore, `.env`, migrations, comprovantes): **[docs/DEPLOY.md](docs/DEPLOY.md)**

```bash
# No dev, antes do go-live:
./scripts/backup-db.sh
```

## Produção (Docker)

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan horizon
```

`scheduler` e `horizon` já sobem com `docker compose up -d` — não precisa de terminais artisan extras.

No Telegram: `/ops` (processos necessários) e `/comandos` (rodar tarefas em segundo plano).

## Filas

- `ocr` — processamento de documentos
- `ai` — análises e insights
- `notifications` — alertas (email, Telegram, WhatsApp)

## Variáveis importantes

```env
AI_PROVIDER=openai
OPENAI_API_KEY=
OPENROUTER_API_KEY=
OCR_PROVIDER=tesseract
TELEGRAM_BOT_TOKEN=
QUEUE_CONNECTION=redis
```

## Scheduler

Diariamente às 06:00: alertas, snapshots de caixa, previsões, análise IA e observabilidade.
