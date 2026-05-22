# Fase 1 — Fundação (backend) — Concluída

Checklist de verificação da Fase 1 do [ROADMAP](ROADMAP.md).

## Arquitetura

| Item | Status | Verificação |
|------|--------|-------------|
| Módulos desacoplados | ✅ | `Modules/{Core,Finance,Companies,Projects,Categorization,OCR,Intelligence,Alerts,Integrations}` |
| Actions / DTOs / Services / Repositories | ✅ | Ex.: `CreateTransactionAction`, `CreateTransactionData`, `TransactionRepository` |
| Events + Observer | ✅ | `TransactionCreated`, `TransactionObserver`, listener em `FinanceModule` |
| Feature flags | ✅ | `App\Core\Support\FeatureFlag` |
| API v1 + Sanctum | ✅ | `Modules/Core/Presentation/Routes/api.php` |
| Middleware workspace | ✅ | `EnsureWorkspaceAccess` |

## API v1 (endpoints)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/dashboard` | CFO dashboard |
| CRUD | `/api/v1/transactions` | Lançamentos + recorrência |
| CRUD | `/api/v1/companies` | Empresas |
| CRUD | `/api/v1/projects` | Projetos |
| GET/POST | `/api/v1/categories` | Classificação |
| GET/POST | `/api/v1/accounts` | Contas financeiras |
| GET | `/api/v1/recurring-items` | Recorrências |
| POST | `/api/v1/imports/ofx` | Import OFX |
| POST | `/api/v1/imports/csv` | Import CSV |
| GET/PATCH | `/api/v1/alerts` | Alertas |
| GET/POST | `/api/v1/ai/*` | Insights + assistente + análise |
| POST | `/api/v1/documents` | Upload OCR |

Header: `Authorization: Bearer {token}` + `X-Workspace-Id: 1`

## Domínio financeiro

| Capacidade | Implementação |
|------------|---------------|
| Categorização (regras + padrões + IA) | `CategorizationService` (slug → `category_id` corrigido) |
| Recorrência em transações | `RecurringItem` + `is_recurring` / `recurrence_frequency` |
| Fluxo de caixa | `CashFlowService` + snapshots |
| Previsão 90 dias | `ForecastService` |
| Alertas automáticos | `AlertDetectionService` |
| OCR assíncrono | `ProcessOcrJob` (fila `ocr`) |
| IA ecossistema + observabilidade | `RunFinancialAnalysisJob`, `RunObservabilityAnalysisJob` |
| Import extratos | `StatementImportService` + API |

## Comandos Artisan

```bash
php artisan financial:daily          # Rotina das 06:00 (alertas, caixa, previsão, IA)
php artisan financial:scan-alerts    # Somente detecção de alertas
php artisan financial:daily --workspace=1
```

Scheduler: `routes/console.php` → `financial:daily` às 06:00.

## Filas

| Fila | Uso |
|------|-----|
| `ocr` | Documentos |
| `ai` | Análises e observabilidade |
| `notifications` | Alertas (email/Telegram/WhatsApp) |
| `default` | Geral |

Dev: `php artisan queue:listen --queue=default,ocr,ai,notifications`  
Prod: `php artisan horizon`

## Segurança (Fase 1)

| Item | Status |
|------|--------|
| Policies workspace | ✅ Transaction, Company, Project |
| Rate limit API | ✅ 120/min |
| RBAC (roles) | 🟡 Roles no seed; permissões granulares na Fase 6 |
| 2FA | 🟡 Pacote instalado; UI Fase 2+ |

## Testes automatizados

O PHP deste ambiente **não tem extensão `pdo_sqlite`** — os testes usam **MySQL** (`financial_test`), não SQLite em memória.

```bash
# 1) Criar banco de testes (uma vez; usa DB_USERNAME/DB_PASSWORD do .env)
bash scripts/setup-mysql-test.sh

# 2) Rodar suite (lê credenciais do .env, ignora DB_USERNAME=sa do shell)
./scripts/test.sh
```

Alternativa com sqlite (se instalar extensão): `sudo apt install php-sqlite3` e reverter `phpunit.xml` para sqlite.

**Erro `could not find driver`:** falta `pdo_sqlite` — use `./scripts/test.sh` em vez de `php artisan test` direto.

Suite `tests/Feature/Phase1/`:

- `CategorizationTest` — padrão OpenAI → categoria IA
- `TransactionApiTest` — API + recorrência
- `DashboardApiTest` — dashboard JSON
- `AlertDetectionTest` — receita não recebida
- `FinancialCommandsTest` — comandos daily/scan-alerts

## Docker / docs

- `docker-compose.yml` — MySQL, Redis, app
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [PROMPT_TRACEABILITY.md](PROMPT_TRACEABILITY.md)

## Próximo passo

**Fase 2** — gráficos, UX avançada, 2FA web, notificações completas.
