# Rastreabilidade — Prompt inicial × Implementação

Este documento mapeia **cada bloco do prompt original** para **onde está no repositório**, **status** e **fase do [ROADMAP](ROADMAP.md)**.

Legenda: ✅ implementado | 🟡 parcial | ❌ pendente

---

## Stack e princípios arquiteturais

| Requisito | Status | Onde está |
|-----------|--------|-----------|
| Laravel backend | ✅ | Raiz do projeto, `composer.json` |
| MySQL | ✅ | `database/migrations/2026_05_21_150000_create_financial_platform_tables.php`, `docker-compose.yml` |
| Redis + Queues | 🟡 | Configurado (`config/queue.php`, Horizon); dev pode usar `database` no `.env` |
| OCR + IA | ✅ | `Modules/OCR/`, `Modules/Intelligence/` |
| Arquitetura modular | ✅ | `Modules/*`, `app/Providers/ModuleServiceProvider.php` |
| SOLID, DTOs, Actions, Services, Repositories | ✅ | Ex.: `CreateTransactionAction`, `CreateTransactionData`, `TransactionRepository` |
| Events | ✅ | `Modules/Finance/Domain/Events/TransactionCreated.php` |
| Jobs | ✅ | `ProcessOcrJob`, `RunFinancialAnalysisJob`, `RunObservabilityAnalysisJob`, `DispatchAlertJob` |
| Observers | ✅ | `Modules/Finance/Infrastructure/Observers/TransactionObserver.php` |
| Policies | 🟡 | `app/Policies/TransactionPolicy.php` (só transações) |
| Cache | 🟡 | Laravel cache; flags sem cache dedicado pesado |
| Logs + auditoria | 🟡 | Spatie Activity Log em `Transaction`; canal `financial` em `config/logging.php` |
| Feature flags | ✅ | `Modules/Core/Infrastructure/Models/FeatureFlag.php`, `config/financial.php` |
| Filas OCR/IA | ✅ | `config/financial.php` → `queues`, jobs com `onQueue('ocr'|'ai')` |
| Controllers finos | ✅ | API em `Modules/*/Presentation/Http/Controllers/` |
| API versionada | ✅ | `/api/v1` em `Modules/Core/CoreModule.php` |

**Doc:** [ARCHITECTURE.md](ARCHITECTURE.md) | **Roadmap:** Fase 1 ✅

---

## Papel do sistema (não é CRUD simples)

| Capacidade | Status | Onde está |
|------------|--------|-----------|
| CFO automatizado | 🟡 | `CashFlowService`, `ForecastService`, scheduler `routes/console.php` |
| Central financeira | 🟡 | Dashboard API + web `app/Http/Controllers/Web/DashboardController.php` |
| Assistente IA | ✅ | `FinancialIntelligenceService`, web `AiController`, API `AiAssistantController` |
| Observador automatizado | ✅ | `ObservabilityIntelligenceService`, `RunObservabilityAnalysisJob` |
| Analisador patrimonial | 🟡 | Model `Asset`, soma no dashboard; sem UI dedicada |
| Previsão financeira | ✅ | `ForecastService`, `FinancialForecast` model |
| Inteligência operacional | ✅ | Observabilidade IA + alertas operacionais |

---

## Objetivo principal — funcionalidades

| Funcionalidade | Status | Onde está |
|----------------|--------|-----------|
| Controlar receitas/despesas | ✅ | `transactions`, web `/transactions`, API `api.transactions.*` |
| Controlar empresas | ✅ | `Modules/Companies/`, tipos `CompanyType` (own, partner, payer) |
| Controlar projetos | ✅ | `Modules/Projects/`, ROI em `Project::roi()` |
| Fluxo de caixa | ✅ | `CashFlowService`, snapshots, dashboard |
| Patrimônio | 🟡 | `Asset` model + dashboard; cadastro web pendente |
| Prever receitas/pagamentos | ✅ | `ForecastService`, `RecurringItem` |
| Detectar problemas | ✅ | `AlertDetectionService` |
| Alertas automáticos | ✅ | `Modules/Alerts/`, painel web `/alerts` |
| IA interpretação | ✅ | `Modules/Intelligence/` |
| Categorização automática | ✅ | `CategorizationService`, regras + IA |
| OCR comprovantes | ✅ | `OcrService`, `ProcessOcrJob`, web `/documents` |
| Leitura extratos | 🟡 | `StatementImportService` (OFX/CSV) **sem rota/UI** |
| Cobranças esquecidas | 🟡 | Alertas por recorrência; detecção “assinatura esquecida” limitada |
| Mensalidades não recebidas | ✅ | `detectMissingRevenue()` em `AlertDetectionService` |
| Contas não pagas | ✅ | `detectUnpaidBills()` |
| Gasto anormal | ✅ | `detectAbnormalSpending()` |
| ROI projetos | ✅ | `Projects` model + view `projects/index` |
| Empresas e investimentos | 🟡 | Tipos own/partner/payer; `investment` no enum; UI básica |

---

## Módulo de IA financeira

| Requisito | Status | Onde está |
|-----------|--------|-----------|
| Análise ecossistema completo | ✅ | `RunFinancialAnalysisJob`, `FinancialIntelligenceService::analyzeEcosystem()` |
| Padrões, riscos, desperdícios | ✅ | Prompt `ecosystem_analysis` em `config/financial.php`, `AiInsight` model |
| Assinaturas esquecidas / queda receita | 🟡 | Via insights IA + alertas parciais; sem ML dedicado |
| Inadimplência empresas | 🟡 | Contexto IA; regra explícita fraca |
| Fluxo negativo futuro | ✅ | `detectNegativeForecast()`, `ForecastService` |
| Copiloto / auditor / analista | ✅ | `assistantReply()`, `FinancialContextBuilder` |

**Arquivos-chave:**
- `Modules/Intelligence/Application/Services/FinancialIntelligenceService.php`
- `Modules/Intelligence/Application/Services/FinancialContextBuilder.php`
- `Modules/Intelligence/Application/Jobs/RunFinancialAnalysisJob.php`

---

## IA — troubleshooting e observabilidade

| Requisito | Status | Onde está |
|-----------|--------|-----------|
| Analisar logs, jobs, OCR, filas | ✅ | `ObservabilityIntelligenceService`, `RunObservabilityAnalysisJob` |
| Inconsistências saldo / conciliação | ❌ | Roadmap Fase 3 |
| Sugestões correção / insights | ✅ | `AiInsight` com `suggested_actions` |
| Prompt injection guard | ✅ | `OpenAiProvider::guardPrompt()` |
| Exemplos (“fila OCR acumulando”) | ✅ | Prompt `observability` em `config/financial.php` |

---

## IA assistente operacional

| Pergunta exemplo | Status | Onde está |
|------------------|--------|-----------|
| “Quanto gasto com IA?” | 🟡 | Depende de categorização + contexto IA |
| Empresas atrasando | 🟡 | Contexto em `FinancialContextBuilder` |
| Projeto com prejuízo | 🟡 | Projetos no contexto |
| Fluxo 90 dias | ✅ | `ForecastService` no contexto |
| Riscos relevantes | ✅ | Insights + alertas |

**UI:** `resources/views/ai/assistant.blade.php` | **API:** `POST /api/v1/ai/assistant`

---

## Integrações

| Integração | Status | Onde está |
|------------|--------|-----------|
| Telegram | 🟡 | `TelegramService`, webhooks `Modules/Integrations/Presentation/Routes/webhooks.php` |
| WhatsApp (Evolution opção A) | 🟢 | `EvolutionService`, `WhatsAppService`, webhook + `docs/WHATSAPP_EVOLUTION.md` |
| WhatsApp (opção B — QR por usuário) | ❌ | Roadmap Fase 4 |
| OpenAI / OpenRouter | ✅ | `AiProviderManager`, providers em `Modules/Intelligence/.../Providers/` |
| OCR Tesseract / Vision | ✅ | `OcrProviderManager` |
| OFX / CSV | 🟡 | `StatementImportService` — **sem endpoint** |
| PDF extrato | ❌ | Roadmap Fase 3 |
| Open Finance / bancos | ❌ | `IntegrationConnection` model preparado; Fase 4 |

---

## OCR e interpretação

| Etapa | Status | Onde está |
|-------|--------|-----------|
| Upload PDF/imagem | ✅ | Web `DocumentController`, API `documents.store` |
| Fila OCR | ✅ | `ProcessOcrJob` |
| Extrair valor, data, categoria | ✅ | Providers OCR + categorização pós-OCR |
| Criar transação automática | 🟡 | OCR grava resultado; lançamento manual/pendente |

---

## Categorização automática

| Mecanismo | Status | Onde está |
|-----------|--------|-----------|
| Regras por padrão | ✅ | `CategorizationRule`, `config/financial.php` patterns |
| IA semântica | ✅ | `CategorizationService` + feature flag `ai_categorization` |
| Aprendizado histórico | ❌ | Roadmap Fase 5 |
| UI categorias | ✅ | `CategoryController`, `/categories` |

---

## Módulo de empresas

| Requisito | Status | Onde está |
|-----------|--------|-----------|
| Empresas próprias | ✅ | `CompanyType::Own` |
| Sócio | ✅ | `CompanyType::Partner`, `partnership_share` |
| Me pagam (clientes) | ✅ | `CompanyType::Payer` |
| Investidas | 🟡 | `CompanyType::Investment` no enum |
| Contratos / recorrências | 🟡 | `CompanyContract` model; UI contratos pendente |
| Receitas esperadas | ✅ | `expected_monthly_revenue` |
| Lucro/prejuízo por empresa | ❌ | Relatório dedicado pendente |
| Histórico / status | 🟡 | Models + notas; dashboard empresa pendente |

---

## Módulo de projetos

| Requisito | Status | Onde está |
|-----------|--------|-----------|
| CRUD projetos | ✅ | API + web |
| Custo, retorno, ROI | ✅ | `Project` model, view index |
| Previsão retorno | 🟡 | Campo `expected_return`; sem gráfico |

---

## Alertas inteligentes

| Tipo alerta | Status | Onde está |
|-------------|--------|-----------|
| Mensalidade não recebida | ✅ | `AlertDetectionService::detectMissingRevenue` |
| Conta não paga | ✅ | `detectUnpaidBills` |
| Gasto elevado | ✅ | `detectAbnormalSpending` |
| Previsão caixa negativa | ✅ | `detectNegativeForecast` |
| Painel | ✅ | `/alerts`, dashboard |
| Email | 🟡 | `AlertNotificationService` estrutura; envio depende config SMTP |
| Telegram / WhatsApp | 🟡 | `DispatchAlertJob` + services; bots incompletos |
| Falhas OCR / filas | ✅ | Observabilidade IA + métricas |

---

## Segurança

| Requisito | Status | Onde está |
|-----------|--------|-----------|
| RBAC | 🟡 | Spatie roles no seeder; poucas policies |
| Auth API | ✅ | Sanctum `routes/api.php` |
| Auth web | ✅ | `AuthController`, sessão |
| 2FA | 🟡 | Pacote instalado, colunas `users`; **fluxo UI não implementado** |
| Rate limit | ✅ | `AppServiceProvider` → `api` 120/min |
| Upload proteção | 🟡 | Validação mimes/size em `DocumentController` |
| Workspace isolation | ✅ | `EnsureWorkspaceAccess`, `SetWebWorkspace` |

---

## Frontend / entregáveis UX

| Entregável | Status | Onde está |
|------------|--------|-----------|
| Dashboard web | ✅ | `resources/views/dashboard/`, AdminLTE |
| CRUD transações/empresas/projetos | ✅ | `resources/views/`, `app/Http/Controllers/Web/` |
| Assistente IA (chat) | ✅ | `ai/assistant.blade.php` |
| Insights IA | ✅ | `ai/insights.blade.php` |
| OCR upload | ✅ | `documents/index.blade.php` |
| SPA Vue/React | ❌ | Roadmap Fase 2 |
| Gráficos interativos | ❌ | Roadmap Fase 2 |
| Documentação técnica | ✅ | `docs/ARCHITECTURE.md`, este arquivo |
| Roadmap | ✅ | `docs/ROADMAP.md` |
| Docker produção | ✅ | `docker-compose.yml` |
| Testes automatizados | ❌ | Prioridade imediata no ROADMAP |

---

## Mapa de diretórios (onde procurar)

```
docs/
├── ARCHITECTURE.md      # Fluxos técnicos
├── ROADMAP.md           # Fases 1–7
└── PROMPT_TRACEABILITY.md  # Este arquivo

Modules/
├── Core/                # API v1, workspaces, feature flags
├── Finance/             # Transações, fluxo, previsão, import OFX (service)
├── Companies/           # Empresas, contratos
├── Projects/            # Projetos, ROI
├── Categorization/      # Categorias e regras
├── OCR/                 # Documentos e jobs OCR
├── Intelligence/        # IA, observabilidade, assistente
├── Alerts/              # Detecção e notificações
└── Integrations/        # Telegram, WhatsApp, webhooks

app/Http/Controllers/Web/   # UI AdminLTE (painel)
routes/web.php              # Rotas web (sem /api/)
Modules/Core/Presentation/Routes/api.php  # API v1

database/migrations/2026_05_21_150000_create_financial_platform_tables.php
config/financial.php       # IA, OCR, padrões categorização, filas
```

---

## Resumo executivo (% estimado do prompt)

| Área | Progresso |
|------|-----------|
| Arquitetura backend / módulos | ~90% |
| API + domínio financeiro core | ~85% |
| IA + OCR (backend) | ~75% |
| Alertas (regras) | ~80% |
| UI web AdminLTE | ~55% |
| Integrações externas (Telegram/WA/email) | ~25% |
| Import extratos / conciliação | ~20% |
| Segurança enterprise (2FA, RBAC completo) | ~40% |
| SaaS billing / multi-org | 0% (Fase 6) |

---

## Próximos passos alinhados ao prompt (ordem sugerida)

1. **Expor import OFX/CSV** — controller + tela em Finance (Fase 3 do roadmap)
2. **Completar notificações** — email + Telegram com tokens no `.env`
3. **2FA fluxo web** — usar `pragmarx/google2fa-laravel`
4. **Patrimônio UI** — CRUD `assets`
5. **Contratos por empresa** — UI sobre `company_contracts`
6. **Testes** nos Services críticos (`AlertDetectionService`, `CategorizationService`, `ForecastService`)
7. **Gráficos dashboard** — Fase 2 roadmap

Atualize este arquivo quando fechar itens do roadmap.
