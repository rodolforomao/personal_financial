# Roadmap — Financial Intelligence Platform

**Estrutura atual:** [PLATFORM.md](PLATFORM.md) · **Arquitetura:** [ARCHITECTURE.md](ARCHITECTURE.md)

---

## Fase 1 — Fundação ✅ concluída

- [x] Arquitetura modular enterprise (10 módulos em `ModuleServiceProvider`)
- [x] Migrations e models completos
- [x] Módulos: Core, Finance, Companies, Projects, Operations, Categorization, OCR, Intelligence, Alerts, Integrations
- [x] API REST v1 com Sanctum (+ categorias, contas, recorrências, import OFX/CSV)
- [x] Categorização (regras + padrões + IA, resolução `category_id`)
- [x] Filas OCR / IA / Notificações + comandos `financial:daily` / `financial:scan-alerts`
- [x] Dashboard e previsões
- [x] Alertas inteligentes (detecção + jobs)
- [x] Policies workspace (Transaction, Company, Project)
- [x] Testes Phase1 + unit (`./scripts/test.sh`)
- [x] Docker Compose produção
- [x] Documentação: [PLATFORM.md](PLATFORM.md), [ARCHITECTURE.md](ARCHITECTURE.md)

---

## Fase 2 — Frontend e UX

**Concluído (~75%):**

- [x] Painel web AdminLTE + Bootstrap — `app/Http/Controllers/Web/`
- [x] Login, registro, recuperação de senha
- [x] Dashboard com filtros + gráficos Chart.js (fluxo 6 meses, categorias, patrimônio)
- [x] CRUD transações, empresas, projetos, patrimônio (`/assets`)
- [x] Inbox alertas (`/alerts`) e insights IA (`/ai/insights`)
- [x] Chat assistente (`/ai/assistant`) + config IA por workspace (`/ai/settings`)
- [x] Upload comprovantes (`/documents`) + N comprovantes por transação + extração OCR no formulário
- [x] Relatórios financeiros (`/reports`)
- [x] Higiene de dados (`/data-hygiene`) — correções em lote
- [x] Regras de categorização (`/categorization-rules`) + regras compartilhadas
- [x] Bulk actions em transações (categorizar, atualizar, excluir, estornos)
- [x] Segurança conta: sessões e tokens API (`/account/security`)
- [x] Observabilidade web (`/observability`)

**Pendente:**

- [ ] SPA (Vue/React) com dashboards interativos
- [ ] Upload drag-and-drop (melhorar UX)
- [ ] **2FA fluxo web completo** (pacote instalado; colunas `users` prontas)

---

## Fase 3 — Importação e conciliação

**Concluído (~70%):**

- [x] Importador OFX/CSV na UI (`/statements`) + API
- [x] Mapeamento CSV customizado (`/statements/import/csv-map`)
- [x] Conciliação extrato ↔ lançamentos (`StatementLineMatcher`, tela reconcile)
- [x] Confirmar todos sugeridos / importar não conciliados
- [x] Limpeza pares de estorno (`remove-estorno-pairs`)

**Pendente:**

- [ ] Import PDF de extrato
- [ ] Detecção de divergências de saldo
- [ ] Matching inteligente com IA (além de regras data/valor/descrição)

---

## Fase 4 — Integrações

**Concluído (~70%):**

- [x] WhatsApp alertas via **Evolution API — Opção A** — [WHATSAPP_EVOLUTION.md](WHATSAPP_EVOLUTION.md)
- [x] Telegram inbound — texto livre, comandos, anti-duplicata — [TELEGRAM_INBOUND.md](TELEGRAM_INBOUND.md)
- [x] Comprovante Telegram/WhatsApp — OCR, SIM/NÃO, anexo, parser BR
- [x] **Gmail** — OAuth, sync agendado (`gmail:sync`), parser e-mail → transação
- [x] Import extrato via Telegram (comandos dedicados)

**Pendente:**

- [ ] **WhatsApp Opção B** — instância por usuário (QR na UI)
- [ ] Open Finance (plug-in desacoplado)
- [ ] Conectores bancários

---

## Fase 5 — Domínio operacional e IA avançada

**Concluído (parcial):**

- [x] Módulo **Operations** — operações e unidades (`/operations`)
- [x] **Salários CLT** — cadastro, períodos, confirmação (`/clt-salaries`)
- [x] Marcos de projeto (`project_milestones`)

**Pendente (IA contínuo):**

- [ ] Aprendizado de categorização por histórico do usuário
- [ ] Detecção de assinaturas esquecidas (ML)
- [ ] Simulações de cenário ("e se receita cair 20%?")
- [ ] Relatórios PDF gerados por IA
- [ ] Embeddings para busca semântica em transações

---

## Fase 6 — Enterprise SaaS

**Concluído (parcial ~25%):**

- [x] Convites workspace (`workspace_invites`, UI `/workspace`)
- [x] Admin plataforma — usuários, acesso, perfis de assinatura (`/admin/users`)
- [x] Pagamento PIX manual + QR (`/subscription/pending`) — sem gateway Stripe
- [x] Configurações globais (`platform_settings`, `/admin/settings`)
- [x] Menu navegação configurável (`navigation_menu_items`)

**Pendente:**

- [ ] Billing e planos (Stripe ou similar)
- [ ] SSO (Google, Microsoft)
- [ ] API pública versionada (v2)
- [ ] SLA e monitoramento (Datadog/Sentry)
- [ ] RBAC granular completo (além do seed)

---

## Fase 7 — Mobile e automação

- [ ] App mobile (captura de recibos)
- [ ] Notificações push
- [ ] Automações tipo Zapier internas
- [ ] Regras de workflow customizáveis

---

## Prioridades imediatas recomendadas

1. **2FA web** — ativar fluxo com `pragmarx/google2fa-laravel` em `/account/security`
2. **Divergência de saldo** pós-importação de extrato (Fase 3)
3. **Correção fluxo comprovante** — responder valor/data antes de salvar (Telegram/WhatsApp)
4. Redis em staging + Horizon (produção já no Docker Compose)
5. Hardening produção — HTTPS, backups ([DEPLOY.md](DEPLOY.md)), rate limits
6. Open Finance / conectores bancários (Fase 4)
