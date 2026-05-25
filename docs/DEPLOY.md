# Passo a passo — Deploy para produção

Guia operacional para subir o **Financial Intelligence** com **dados reais** do ambiente local, sem misturar dev com produção e sem perder comprovantes.

Documento complementar (conceitos): [DADOS_E_PRODUCAO.md](DADOS_E_PRODUCAO.md)

---

## Visão geral dos ambientes

| Ambiente | `APP_ENV` | Banco MySQL | Uso |
|----------|-----------|-------------|-----|
| Local (seu PC) | `local` | `financial` | Testes com dados reais |
| Testes PHPUnit | `testing` | `financial_test` | Automático — não usar para deploy |
| Produção | `production` | `financial_prod` (ou outro nome) | Sistema definitivo |

**Regra de ouro:** código sobe pelo Git; **dados** (SQL + arquivos) sobem por backup/cópia controlada; **migrations** só alteram estrutura.

---

## Fase 0 — Antes de qualquer deploy

### 0.1 Backup do banco local (obrigatório)

Na máquina de desenvolvimento, na raiz do projeto:

```bash
cd /caminho/para/financial_project
./scripts/backup-db.sh
```

**Saída esperada (exemplo):**

```text
Backup: financial @ 127.0.0.1:3306 (utilizador: root)
Destino: .../storage/backups/financial_2026-05-22_174733.sql.gz
-rw-rw-r-- 1 user user 54K ... financial_2026-05-22_174733.sql.gz

Lembrete: copie também storage/app/private (comprovantes) se for restaurar em outro servidor.
```

O arquivo fica em `storage/backups/` (não vai para o Git). Guarde uma cópia em local seguro (nuvem, outro disco).

**O script usa as credenciais do `.env` do projeto** (via Laravel), não o `~/.my.cnf` do Linux. Se `mysqldump` falhar com *Access denied* mas o site local funciona, veja [Problemas comuns — backup](#problemas-comuns).

### 0.2 Backup dos arquivos (comprovantes / OCR)

```bash
tar -czf storage-backup-$(date +%Y%m%d).tar.gz -C storage/app private documents 2>/dev/null || \
tar -czf storage-backup-$(date +%Y%m%d).tar.gz -C storage/app private
```

Comprovantes ficam em `storage/app/private` (e possivelmente `storage/app/documents`).

### 0.3 Conferir o que sobe no Git

| Pode commitar | Nunca commitar |
|---------------|----------------|
| Código, `database/migrations`, `docs/` | `.env` |
| `.env.example` (sem segredos) | `storage/backups/*.sql.gz` |
| | Dumps SQL com dados reais |

### 0.4 Testes (opcional, recomendado)

```bash
./scripts/setup-mysql-test.sh   # uma vez
./scripts/test.sh
```

Usa o banco `financial_test` — **não** altera o `financial` do seu dia a dia.

---

## Fase 1 — Preparar o servidor de produção

### 1.1 Requisitos no servidor

- PHP 8.3+ (extensões: pdo_mysql, mbstring, xml, curl, zip, gd ou imagick se OCR local)
- MySQL 8+
- Redis (recomendado para filas/cache em produção)
- Nginx ou Apache + HTTPS
- Composer, Node (se for build de assets no servidor)
- `mysqldump` / `mysql` client (para restore de backup)

### 1.2 Criar banco dedicado (não reutilizar o `financial` de dev)

No MySQL do servidor (como admin):

```sql
CREATE DATABASE financial_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'financial_app'@'%' IDENTIFIED BY 'SENHA_FORTE_AQUI';
GRANT ALL PRIVILEGES ON financial_prod.* TO 'financial_app'@'%';
FLUSH PRIVILEGES;
```

Ajuste host (`'%'` vs `'localhost'`) conforme sua política de segurança.

### 1.3 Clonar o projeto no servidor

```bash
cd /var/www
git clone <URL_DO_REPOSITORIO> financial
cd financial
git checkout main   # ou a branch/tag de release
```

### 1.4 Criar `.env` de produção (no servidor)

```bash
cp .env.example .env
nano .env   # ou vim
```

**Valores mínimos para produção:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com.br

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=financial_prod
DB_USERNAME=financial_app
DB_PASSWORD=SENHA_FORTE_AQUI

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database

# IA, Telegram, Evolution — chaves e URLs de PRODUÇÃO
OPENAI_API_KEY=...
TELEGRAM_BOT_TOKEN=...          # bot de produção (diferente do dev, se possível)
TELEGRAM_INBOUND_ENABLED=true
EVOLUTION_API_URL=...
```

**Não** copie o `.env` do seu PC para o servidor sem revisar cada variável.

---

## Fase 2 — Primeira instalação (servidor vazio)

Execute **no servidor**, na pasta do projeto:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate

# Estrutura do banco (tabelas vazias)
php artisan migrate --force

# Permissões
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

### O que NÃO fazer em produção na primeira vez

```bash
# NÃO rode em produção (cria usuário demo e dados de exemplo):
php artisan db:seed
php artisan migrate:fresh
php artisan db:wipe
```

Com `APP_ENV=production`, o Laravel **bloqueia** `migrate:fresh`, `db:wipe` e `migrate:refresh`.

### 2.1 Criar usuário administrador real

Crie o primeiro usuário pela aplicação ou via tinker — **não** use `admin@financial.local` / `password` de demo.

### 2.2 Cache de configuração (produção)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2.3 Filas e rotina diária

```bash
# Supervisor ou systemd para Horizon / queue worker
php artisan horizon

# Cron (usuário www-data ou deploy)
* * * * * cd /var/www/financial && php artisan schedule:run >> /dev/null 2>&1
```

O scheduler executa `financial:daily` (alertas, caixa, CLT, etc.) — ver `routes/console.php`.

### 2.4 Build de assets (se usar Vite no deploy)

```bash
npm ci
npm run build
```

---

## Fase 3 — Levar dados do ambiente local para produção (go-live)

Use esta fase quando já tiver **dados reais** no PC (como no seu backup de ~54K).

### 3.1 No PC de desenvolvimento — gerar backup

```bash
./scripts/backup-db.sh
```

Anote o caminho do arquivo, por exemplo:

`storage/backups/financial_2026-05-22_174733.sql.gz`

### 3.2 Copiar backup e arquivos para o servidor

```bash
# Exemplo com scp (ajuste usuário, host e caminhos)
scp storage/backups/financial_2026-05-22_174733.sql.gz deploy@seu-servidor:/tmp/
scp storage-backup-20260522.tar.gz deploy@seu-servidor:/tmp/
```

Use canal seguro (SSH). Não envie dumps por e-mail ou chat.

### 3.3 No servidor — restaurar o banco

**Pré-requisito:** `php artisan migrate --force` já executado (Fase 2).

```bash
cd /var/www/financial

# Restaurar dump no banco de PRODUÇÃO
gunzip -c /tmp/financial_2026-05-22_174733.sql.gz | \
  mysql -h 127.0.0.1 -u financial_app -p financial_prod
```

Se o dump foi feito do banco `financial` local, os dados entram em `financial_prod`. Revise se não há referências a ambiente antigo no `metadata` das transações (geralmente inofensivo).

### 3.4 No servidor — restaurar comprovantes

```bash
cd /var/www/financial
tar -xzf /tmp/storage-backup-20260522.tar.gz -C storage/app
chown -R www-data:www-data storage
```

### 3.5 Pós-restore — segurança e integrações

- [ ] Alterar senhas de usuários se o dump veio de dev
- [ ] Confirmar `APP_URL` e HTTPS
- [ ] Telegram: `php artisan telegram:webhook-sync` com URL pública HTTPS
- [ ] Evolution: webhook apontando para produção
- [ ] Testar login, listagem de transações, um comprovante anexado
- [ ] Rodar `php artisan config:cache` novamente após mudanças no `.env`

---

## Fase 4 — Deploys seguintes (atualização de código)

Quando já existe produção e você só publica **nova versão** do código:

### 4.1 No servidor

```bash
cd /var/www/financial

# Backup de produção ANTES de atualizar
# (crie script similar ou mysqldump manual do financial_prod)
mysqldump -u financial_app -p financial_prod | gzip -9 > /var/backups/financial_prod_$(date +%F).sql.gz

git pull origin main
composer install --no-dev --optimize-autoloader

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reiniciar workers
php artisan horizon:terminate
# ou supervisor restart
```

### 4.2 Comandos proibidos em produção

| Comando | Motivo |
|---------|--------|
| `migrate:fresh` | Apaga todas as tabelas e dados |
| `migrate:refresh` | Rollback + migrate — perde dados |
| `db:wipe` | Esvazia o banco |
| `db:seed` | Dados de demonstração |

Comando **seguro:** `php artisan migrate --force` (só migrations pendentes).

---

## Fase 5 — Alternativa: produção limpa (sem importar dev)

Se preferir **não** levar o histórico de testes do PC:

1. Fase 2 completa (migrate, usuário admin novo)
2. Cadastrar empresas, categorias, transações direto em produção
3. Manter o backup local apenas como arquivo de contingência

---

## Checklist final de go-live

### Backup e dados

- [ ] `./scripts/backup-db.sh` executado com sucesso no dev
- [ ] Arquivo `.sql.gz` guardado fora do repositório
- [ ] `storage/app/private` empacotado e copiado
- [ ] Restore testado em staging (opcional, recomendado)

### Servidor

- [ ] `APP_ENV=production` e `APP_DEBUG=false`
- [ ] `DB_DATABASE` diferente do local (`financial_prod`)
- [ ] HTTPS ativo
- [ ] Filas (Horizon ou `queue:work`) rodando
- [ ] Cron do `schedule:run` configurado

### Integrações

- [ ] Bot Telegram / Evolution de produção (tokens e webhooks)
- [ ] `OPENAI_API_KEY` válida e com limite de custo
- [ ] `poppler-utils` (`pdftoppm`) instalado se usar OCR de PDF

### Aplicação

- [ ] Login com usuário real (não demo)
- [ ] Transações e comprovantes visíveis
- [ ] Salário CLT / alertas testados se usar

---

## Problemas comuns

### Backup: `Access denied for user 'root'@'localhost'`

**Causa frequente:** o ficheiro `~/.my.cnf` no Linux tem `user`/`password` diferentes do `.env` do projeto. O `mysqldump` misturava essa configuração.

**Solução:** use sempre `./scripts/backup-db.sh` (versão atual usa credenciais do Laravel e `--defaults-file`).

**Testar ligação igual ao Laravel:**

```bash
php artisan db:show
```

### Backup: senha com `!` ou caracteres especiais no `.env`

O script lê a password via PHP (bootstrap Laravel), não via `grep` no bash — evita erro com `!`.

### Restore: banco errado

Confirme o nome antes de importar:

```bash
# Local
grep DB_DATABASE .env

# Produção (no servidor)
grep DB_DATABASE .env
```

Nunca restaure um dump de dev em cima do banco de testes automatizados em produção por engano.

### `migrate:fresh` em produção

Bloqueado pelo projeto quando `APP_ENV=production`. Se precisar recomeçar o schema em produção, faça backup, drop manual controlado e `migrate --force` — **somente** com janela de manutenção e plano explícito.

---

## Referência rápida de comandos

| Ação | Onde | Comando |
|------|------|---------|
| Backup SQL dev | PC | `./scripts/backup-db.sh` |
| Backup arquivos | PC | `tar -czf storage-backup-....tar.gz -C storage/app private` |
| Migrations | Servidor | `php artisan migrate --force` |
| Cache prod | Servidor | `php artisan config:cache` |
| Webhook Telegram | Servidor | `php artisan telegram:webhook-sync` |
| Testes | PC | `./scripts/test.sh` |
| Rotina diária | Servidor (cron) | `php artisan schedule:run` |

---

## Histórico deste documento

| Data | Notas |
|------|--------|
| 2026-05-22 | Primeira versão — backup via `./scripts/backup-db.sh`, go-live com dados reais locais |
