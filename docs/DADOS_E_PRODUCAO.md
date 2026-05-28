# Dados reais em desenvolvimento e subida para produção

Você está cadastrando **dados financeiros reais** no ambiente local. Isso é ótimo para testar, mas exige disciplina para não misturar dev com produção nem perder informação na migração.

**Passo a passo operacional de deploy:** [DEPLOY.md](DEPLOY.md)

## Princípios

| Regra | Por quê |
|--------|---------|
| **Banco de dev ≠ banco de produção** | Nomes, host e senha diferentes (`financial` vs `financial_prod`) |
| **Migrations só alteram estrutura** | `php artisan migrate` cria colunas/tabelas; **não** copia seus lançamentos |
| **Dados sobem à parte** | Export/import ou deploy inicial manual — nunca `db:seed` em produção com seed de demo |
| **Arquivos ≠ Git** | Comprovantes ficam em `storage/app/private` (já ignorado pelo Git) |
| **Testes usam outro banco** | `financial_test` — nunca o mesmo banco que você usa no dia a dia |

## Ambientes recomendados

```text
local (APP_ENV=local)     → DB: financial          → dados reais de teste
testing (phpunit)         → DB: financial_test    → apagado/recriado pelos testes
staging (opcional)        → DB: financial_staging → cópia anonimizada ou vazia
production (APP_ENV=production) → DB: financial_prod → dados definitivos
```

No `.env` de **produção** (servidor, nunca commitado):

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_DATABASE=financial_prod` (ou outro nome exclusivo)
- Chaves de API e Telegram **de produção** (bots/webhooks separados do dev)

## O que pode ir para o Git

| Pode | Não pode |
|------|----------|
| Código, migrations, `docs/` | `.env` (senhas, API keys) |
| `.env.example` (sem segredos) | Dump SQL com transações reais |
| Estrutura vazia em `storage/` | `storage/app/private/documents/…` (comprovantes) |

## Comandos perigosos (só em dev, com backup)

Estes **apagam dados**:

```bash
php artisan migrate:fresh    # drop + migrate + perde tudo
php artisan migrate:refresh  # rollback + migrate
php artisan db:wipe          # esvazia o banco
```

Em `APP_ENV=production` o Laravel **bloqueia** esses comandos (configurado no projeto).

**Seguro em qualquer ambiente:**

```bash
php artisan migrate          # só aplica migrations novas
```

## Backup antes de mudanças grandes

```bash
./scripts/backup-db.sh
```

Gera `storage/backups/financial_YYYY-MM-DD_HHMMSS.sql.gz` (pasta ignorada pelo Git). Usa credenciais do `.env` do projeto (ignora `~/.my.cnf` do sistema).

**Exemplo de sucesso:**

```text
Backup: financial @ 127.0.0.1:3306 (utilizador: root)
Destino: .../storage/backups/financial_2026-05-22_174733.sql.gz
-rw-rw-r-- 1 user user 54K ... financial_2026-05-22_174733.sql.gz
```

Restaurar (exemplo em **produção**, banco `financial_prod`):

```bash
gunzip -c storage/backups/financial_2026-05-22_174733.sql.gz | mysql -u financial_app -p financial_prod
```

**Comprovantes:** copie também `storage/app/private` (e `storage/app/documents` se existir) no mesmo backup de arquivos.

## Subir para produção (fluxo sugerido)

### 1. Primeira vez (produção vazia)

1. Servidor com MySQL dedicado, `.env` de produção
2. `composer install --no-dev`
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. **Não** rodar `db:seed` (cria usuário demo `admin@financial.local`)
6. Criar usuário admin real: `php artisan tinker` ou tela de registro (se houver)
7. Configurar workspace, categorias (seeder opcional só de categorias — ver abaixo)

### 2. Levar dados do seu dev atual para produção

**Opção A — Export/import MySQL (comum para go-live)**

```bash
# No dev
./scripts/backup-db.sh

# Copiar o .sql.gz para o servidor (scp, rsync — canal seguro)
# No servidor (banco vazio já migrado)
gunzip -c financial_XXXX.sql.gz | mysql -u USER -p financial_prod
```

Depois copiar `storage/app/private` para o mesmo path no servidor.

**Opção B — Começar produção do zero**

Manter dev como histórico de testes; em produção cadastrar de novo só o que for definitivo.

**Opção C — Staging no meio**

Restaurar backup em `financial_staging`, validar, depois repetir export para produção.

### 3. Atualizações de código (deploy contínuo)

1. Backup automático do banco de produção (diário)
2. Deploy do código novo
3. `php artisan migrate --force` (só estrutura)
4. `php artisan config:cache` / `route:cache` em produção
5. **Nunca** `migrate:fresh` em produção

## Telegram / WhatsApp / IA

- Use **bot de teste** ou `telegram:poll` em dev; webhook de produção com URL HTTPS separada
- Não reutilize o mesmo `TELEGRAM_BOT_TOKEN` em dev e prod se quiser evitar mensagens cruzadas
- `OPENAI_API_KEY`: pode ser a mesma conta, mas monitore custo; em prod use limites/rate limit

## Testes automatizados

```bash
./scripts/setup-mysql-test.sh   # uma vez
./scripts/test.sh               # usa financial_test, não mexe no financial
```

## Checklist antes do go-live

- [ ] `.env` de produção com `APP_DEBUG=false` e banco exclusivo
- [ ] Backup recente do dev (`./scripts/backup-db.sh` + pasta `storage/app/private`)
- [ ] Migrations aplicadas em prod (`migrate --force`)
- [ ] Comprovantes/documentos copiados para o servidor
- [ ] Senhas e 2FA dos usuários redefinidas se o dump veio de dev
- [ ] Webhooks (Telegram/Evolution) apontando para URL de produção
- [ ] Confirmar que `financial_test` não é o banco de produção

## Referências no repositório

- [PLATFORM.md](PLATFORM.md#testes) — testes e banco `financial_test`
- [ROADMAP.md](ROADMAP.md) — hardening e backups em produção
