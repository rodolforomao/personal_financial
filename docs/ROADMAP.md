# Roadmap — Financial Intelligence Platform

## Fase 1 — Fundação ✅ concluída

- [x] Arquitetura modular enterprise
- [x] Migrations e models completos
- [x] Módulos: Finance, Companies, Projects, OCR, Intelligence, Alerts, Categorization, Integrations
- [x] API REST v1 com Sanctum (+ categorias, contas, recorrências, import OFX/CSV)
- [x] Categorização (regras + padrões + IA, resolução `category_id`)
- [x] Filas OCR / IA / Notificações + comandos `financial:daily` / `financial:scan-alerts`
- [x] Dashboard e previsões
- [x] Alertas inteligentes (detecção + jobs)
- [x] Policies workspace (Transaction, Company, Project)
- [x] Testes Phase1 (`php artisan test`)
- [x] Docker Compose produção
- [x] Documentação: [PHASE1.md](PHASE1.md), [ARCHITECTURE.md](ARCHITECTURE.md), [PROMPT_TRACEABILITY.md](PROMPT_TRACEABILITY.md)

## Fase 2 — Frontend e UX (4-6 semanas)

- [x] Painel web AdminLTE + Bootstrap (login, dashboard, CRUD básico) — ver `app/Http/Controllers/Web/`
- [x] Inbox de alertas e insights IA (páginas `/alerts`, `/ai/insights`)
- [x] Chat do assistente financeiro (`/ai/assistant`)
- [x] Upload de comprovantes (`/documents`) + vínculo N comprovantes por transação
- [x] **Gráficos** no dashboard (fluxo 6 meses, despesas por categoria, patrimônio) — Chart.js
- [x] **CRUD patrimônio** (`/assets`)
- [ ] SPA (Vue/React) com dashboards interativos
- [ ] Upload drag-and-drop (melhorar UX)

**Rastreabilidade prompt inicial:** [PROMPT_TRACEABILITY.md](PROMPT_TRACEABILITY.md)

## Fase 3 — Importação e conciliação (3-4 semanas)

- [x] Importador **OFX/CSV na UI** (`/statements`) + API legada
- [x] Conciliação extrato ↔ lançamentos (`StatementLineMatcher`, tela de conciliação)
- [ ] Import PDF de extrato
- [ ] Detecção de divergências de saldo
- [ ] Matching inteligente com IA (além de regras data/valor/descrição)

## Fase 4 — Integrações (4-6 semanas)

- [x] WhatsApp alertas via **Evolution API — Opção A** — ver [WHATSAPP_EVOLUTION.md](WHATSAPP_EVOLUTION.md)
- [x] Telegram inbound — texto livre + anti-duplicata — [TELEGRAM_INBOUND.md](TELEGRAM_INBOUND.md)
- [x] **Comprovante Telegram/WhatsApp** — OCR, confirmação SIM/NÃO, anexo à transação, parser BR (`BrazilianAmountParser`, `ReceiptClassifier`)
- [ ] **WhatsApp Opção B** — instância por usuário (QR na UI)
- [ ] Open Finance (plug-in desacoplado)
- [ ] Conectores bancários

## Fase 5 — IA avançada (contínuo)

- [ ] Aprendizado de categorização por histórico do usuário
- [ ] Detecção de assinaturas esquecidas (ML)
- [ ] Simulações de cenário ("e se receita cair 20%?")
- [ ] Relatórios PDF gerados por IA
- [ ] Embeddings para busca semântica em transações

## Fase 6 — Enterprise SaaS (6-8 semanas)

- [ ] Billing e planos (Stripe)
- [ ] Multi-organização com convites
- [ ] SSO (Google, Microsoft)
- [ ] API pública versionada (v2)
- [ ] SLA e monitoramento (Datadog/Sentry)

## Fase 7 — Mobile e automação

- [ ] App mobile (captura de recibos)
- [ ] Notificações push
- [ ] Automações tipo Zapier internas
- [ ] Regras de workflow customizáveis

## Prioridades imediatas recomendadas

1. Configurar Redis em staging + Horizon
2. Correção de campos no fluxo comprovante (responder `valor 5000` antes de salvar)
3. Detecção de divergência de saldo pós-importação
4. 2FA web + hardening produção (HTTPS, backups, rate limits)
5. Open Finance / conectores bancários
