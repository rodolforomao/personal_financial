#!/usr/bin/env bash
# Deploy Financial Intelligence → financialiq.rodolforomao.com.br (Ultrahost / HestiaCP)
# Isolado: não altera outros sites, pools PHP existentes nem bancos de outros projetos.
# Uso: ./scripts/deploy-financialiq.sh [--skip-build] [--skip-db-restore] [--skip-evolution]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${DEPLOY_ENV_FILE:-$ROOT/.env_deploy}"
[[ -f "$ENV_FILE" ]] || { echo "Arquivo não encontrado: $ENV_FILE" >&2; exit 1; }

load_var() {
  local key="$1"
  grep -E "^${key}=" "$ENV_FILE" | head -1 | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/'
}

SSH_HOST="$(load_var SSH_HOST)"
SSH_USER="$(load_var SSH_USER)"
SSH_PORT="$(load_var SSH_PORT)"
SSH_PASSWORD="$(load_var SSH_PASSWORD)"
REMOTE_DIR="$(load_var DEPLOY_REMOTE_DIR)"
DEPLOY_DOMAIN="$(load_var DEPLOY_DOMAIN)"
DEPLOY_PHP_BIN="$(load_var DEPLOY_PHP_BIN)"
DEPLOY_WEB_USER="$(load_var DEPLOY_WEB_USER)"
SERVER_IP="$SSH_HOST"

: "${SSH_HOST:?}"
: "${REMOTE_DIR:?}"
: "${DEPLOY_DOMAIN:?}"

SSH_PORT="${SSH_PORT:-22}"
SSH_USER="${SSH_USER:-root}"
DEPLOY_PHP_BIN="${DEPLOY_PHP_BIN:-/usr/bin/php8.4}"
DEPLOY_WEB_USER="${DEPLOY_WEB_USER:-admin}"
NIP_DOMAIN="financialiq.${SERVER_IP}.nip.io"

SKIP_BUILD=false
SKIP_DB_RESTORE=false
SKIP_EVOLUTION=false
for arg in "$@"; do
  case "$arg" in
    --skip-build) SKIP_BUILD=true ;;
    --skip-db-restore) SKIP_DB_RESTORE=true ;;
    --skip-evolution) SKIP_EVOLUTION=true ;;
  esac
done

RUN() {
  if [[ -n "${SSH_PASSWORD:-}" ]] && command -v sshpass &>/dev/null; then
    sshpass -p "$SSH_PASSWORD" ssh -o StrictHostKeyChecking=accept-new -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "$@"
  else
    ssh -o StrictHostKeyChecking=accept-new -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "$@"
  fi
}

RSYNC_RSH="ssh -o StrictHostKeyChecking=accept-new -p $SSH_PORT"
[[ -n "${SSH_PASSWORD:-}" ]] && command -v sshpass &>/dev/null && \
  RSYNC_RSH="sshpass -p '$SSH_PASSWORD' ssh -o StrictHostKeyChecking=accept-new -p $SSH_PORT"

gen_pass() {
  openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24
}

echo "==> Fase 1: infra isolada no Hestia (só $DEPLOY_DOMAIN)"
RUN "bash -s" <<REMOTE_SETUP
set -euo pipefail
DOMAIN='$DEPLOY_DOMAIN'
NIP='$NIP_DOMAIN'
IP='$SERVER_IP'
REMOTE='$REMOTE_DIR'
WEBUSER='$DEPLOY_WEB_USER'
PHPVER='8.4'

SECRETS="\$REMOTE/.deploy-secrets"
mkdir -p "\$REMOTE"
if [[ -f "\$SECRETS" ]]; then
  set +u
  # shellcheck disable=SC1090
  source "\$SECRETS"
  set -u
fi
DB_PASS="\${DB_PASSWORD:-$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)}"
EVO_PASS="\${EVOLUTION_DB_PASSWORD:-$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)}"
cat > "\$SECRETS" <<EOF
DB_PASSWORD=\$DB_PASS
EVOLUTION_DB_PASSWORD=\$EVO_PASS
EOF
chmod 600 "\$SECRETS"

# Domínio produção (idempotente)
if ! grep -q "DOMAIN='\$DOMAIN'" /usr/local/hestia/data/users/\$WEBUSER/web.conf 2>/dev/null; then
  /usr/local/hestia/bin/v-add-web-domain \$WEBUSER "\$DOMAIN" "\$IP"
fi

# Domínio teste nip.io (sem DNS externo)
if ! grep -q "DOMAIN='\$NIP'" /usr/local/hestia/data/users/\$WEBUSER/web.conf 2>/dev/null; then
  /usr/local/hestia/bin/v-add-web-domain \$WEBUSER "\$NIP" "\$IP" || true
fi

WEBROOT="/home/\$WEBUSER/web/\$DOMAIN"
NIPROOT="/home/\$WEBUSER/web/\$NIP"
mkdir -p "\$REMOTE"

for SITE in "\$WEBROOT" "\$NIPROOT"; do
  [[ -d "\$SITE" ]] || continue
  rm -rf "\$SITE/public_html"
  ln -sfn "\$REMOTE/public" "\$SITE/public_html"
done

# PHP 8.4 só neste domínio (Laravel 13 exige >=8.4)
/usr/local/hestia/bin/v-change-web-domain-backend-tpl \$WEBUSER "\$DOMAIN" PHP-8_4 2>/dev/null || true
/usr/local/hestia/bin/v-change-web-domain-backend-tpl \$WEBUSER "\$NIP" PHP-8_4 2>/dev/null || true

# open_basedir só no pool deste domínio (PHP 8.4)
for POOL in /etc/php/\${PHPVER}/fpm/pool.d/\${DOMAIN}.conf /etc/php/\${PHPVER}/fpm/pool.d/\${NIP}.conf; do
  [[ -f "\$POOL" ]] || continue
  if ! grep -q "financial_project" "\$POOL"; then
    sed -i "s|public_shtml:/home/\$WEBUSER/tmp|public_shtml:/home/\$WEBUSER/web/financial_project/:/home/\$WEBUSER/tmp|g" "\$POOL"
  fi
done
systemctl reload php\${PHPVER}-fpm

# Bancos dedicados (não toca nos existentes)
if ! mysql -e "SHOW DATABASES LIKE 'admin_financialiq';" | grep -q admin_financialiq; then
  /usr/local/hestia/bin/v-add-database \$WEBUSER financialiq financialiq "\$DB_PASSWORD" mysql localhost utf8mb4
fi
if ! mysql -e "SHOW DATABASES LIKE 'admin_evolution';" | grep -q admin_evolution; then
  /usr/local/hestia/bin/v-add-database \$WEBUSER evolution evolution "\$EVO_PASS" mysql localhost utf8mb4
fi

# OCR (pacotes de sistema — seguro para todos os sites)
command -v tesseract >/dev/null || DEBIAN_FRONTEND=noninteractive apt-get install -y tesseract-ocr tesseract-ocr-por poppler-utils

# Permissões pasta do projeto
chown -R \$WEBUSER:\$WEBUSER "\$REMOTE"
chmod 600 "\$SECRETS"

echo "DB_PASSWORD=\$DB_PASSWORD"
echo "EVOLUTION_DB_PASSWORD=\$EVOLUTION_DB_PASSWORD"
REMOTE_SETUP

echo "==> Fase 2: build assets (local)"
if [[ "$SKIP_BUILD" == false ]]; then
  npm ci --silent 2>/dev/null || npm install --silent
  npm run build
fi

echo "==> Fase 3: rsync código (exclui .env, logs, vendor)"
RUN "mkdir -p $REMOTE_DIR && chown -R $DEPLOY_WEB_USER:$DEPLOY_WEB_USER $REMOTE_DIR"
rsync -avz --delete -e "$RSYNC_RSH" \
  --exclude node_modules --exclude .git --exclude .env --exclude .env_deploy \
  --exclude vendor --exclude storage/logs --exclude 'storage/framework/cache/data/*' \
  --exclude .deploy-secrets \
  --exclude 'storage/backups/*.sql.gz' \
  "$ROOT/" "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"

echo "==> Fase 4: .env produção no servidor"
DB_PASS=$(RUN "grep DB_PASSWORD= $REMOTE_DIR/.deploy-secrets 2>/dev/null | cut -d= -f2" || true)
EVO_PASS=$(RUN "grep EVOLUTION_DB_PASSWORD= $REMOTE_DIR/.deploy-secrets 2>/dev/null | cut -d= -f2" || true)
ENV_TMP=$(mktemp)
{
  sed -n '/^DB_CONNECTION=/,/^EVOLUTION_CONTAINER_NAME=/p' "$ENV_FILE" | grep -E '^(DB_|EVOLUTION_DB_|EVOLUTION_DOCKER|EVOLUTION_CONTAINER)' || true
  sed -n '/^APP_NAME=/,$p' "$ENV_FILE"
} | while IFS= read -r line || [[ -n "$line" ]]; do
  [[ "$line" =~ ^SSH_ ]] && continue
  [[ "$line" =~ ^HESTIA_ ]] && continue
  [[ "$line" =~ ^DEPLOY_ ]] && continue
  [[ "$line" =~ ^EVOLUTION_DOCKER ]] && continue
  [[ "$line" =~ ^EVOLUTION_CONTAINER ]] && continue
  if [[ "$line" == "DB_PASSWORD=" && -n "$DB_PASS" ]]; then
    echo "DB_PASSWORD=$DB_PASS"
  elif [[ "$line" == "EVOLUTION_DB_PASSWORD=" && -n "$EVO_PASS" ]]; then
    echo "EVOLUTION_DB_PASSWORD=$EVO_PASS"
  else
    echo "$line"
  fi
done > "$ENV_TMP"
rsync -avz -e "$RSYNC_RSH" "$ENV_TMP" "$SSH_USER@$SSH_HOST:$REMOTE_DIR/.env"
rm -f "$ENV_TMP"
RUN "chown $DEPLOY_WEB_USER:$DEPLOY_WEB_USER $REMOTE_DIR/.env && chmod 600 $REMOTE_DIR/.env"

echo "==> Fase 5: composer + artisan"
RUN "cd $REMOTE_DIR && chown -R $DEPLOY_WEB_USER:$DEPLOY_WEB_USER . && sudo -u $DEPLOY_WEB_USER $DEPLOY_PHP_BIN /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction && sudo -u $DEPLOY_WEB_USER $DEPLOY_PHP_BIN artisan migrate --force && sudo -u $DEPLOY_WEB_USER chmod -R 775 storage bootstrap/cache"

if [[ "$SKIP_DB_RESTORE" == false ]]; then
  BACKUP=$(ls -t "$ROOT/storage/backups/"financial_*.sql.gz 2>/dev/null | head -1)
  [[ -n "$BACKUP" ]] || { echo "Backup não encontrado" >&2; exit 1; }
  echo "==> Fase 6: restore banco ($BACKUP)"
  rsync -avz -e "$RSYNC_RSH" "$BACKUP" "$SSH_USER@$SSH_HOST:/tmp/financial_restore.sql.gz"
  RUN "gunzip -c /tmp/financial_restore.sql.gz | mysql admin_financialiq && rm -f /tmp/financial_restore.sql.gz && cd $REMOTE_DIR && sudo -u $DEPLOY_WEB_USER $DEPLOY_PHP_BIN artisan migrate --force"

  echo "==> Fase 7: restore comprovantes"
  tar -czf /tmp/financial_storage_private.tar.gz -C "$ROOT/storage/app" private 2>/dev/null || true
  if [[ -f /tmp/financial_storage_private.tar.gz ]]; then
    rsync -avz -e "$RSYNC_RSH" /tmp/financial_storage_private.tar.gz "$SSH_USER@$SSH_HOST:/tmp/"
    RUN "tar -xzf /tmp/financial_storage_private.tar.gz -C $REMOTE_DIR/storage/app && rm -f /tmp/financial_storage_private.tar.gz && chown -R $DEPLOY_WEB_USER:$DEPLOY_WEB_USER $REMOTE_DIR/storage"
    rm -f /tmp/financial_storage_private.tar.gz
  fi
fi

echo "==> Fase 8: cache + systemd (só financial-*)"
RUN "bash -s" <<'REMOTE_SERVICES'
set -euo pipefail
REMOTE='/home/admin/web/financial_project'
PHP='/usr/bin/php8.4'

cd "$REMOTE"
sudo -u admin "$PHP" artisan config:cache
sudo -u admin "$PHP" artisan route:cache
sudo -u admin "$PHP" artisan view:cache

cat > /etc/systemd/system/financial-queue.service <<EOF
[Unit]
Description=Financial IQ Queue Worker
After=network.target mariadb.service redis-server.service

[Service]
Type=simple
User=admin
Group=admin
WorkingDirectory=$REMOTE
ExecStart=$PHP $REMOTE/artisan queue:listen redis --sleep=3 --tries=3 --queue=notifications,default,ocr,ai
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/financial-scheduler.service <<EOF
[Unit]
Description=Financial IQ Scheduler
After=network.target mariadb.service

[Service]
Type=simple
User=admin
Group=admin
WorkingDirectory=$REMOTE
ExecStart=$PHP $REMOTE/artisan schedule:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl disable financial-horizon 2>/dev/null || true
systemctl stop financial-horizon 2>/dev/null || true
systemctl daemon-reload
systemctl enable financial-queue financial-scheduler
systemctl restart financial-queue financial-scheduler
REMOTE_SERVICES

if [[ "$SKIP_EVOLUTION" == false ]]; then
  echo "==> Fase 9: Evolution Docker (porta 8085 — 8081 é reservado pelo Hestia)"
  "$ROOT/scripts/deploy-evolution-prod.sh"
fi

echo "==> Fase 10: SSL nip.io (teste imediato)"
RUN "/usr/local/hestia/bin/v-add-letsencrypt-domain admin '$NIP_DOMAIN' '' '' 2>/dev/null || true; /usr/local/hestia/bin/v-add-web-domain-ssl-force admin '$NIP_DOMAIN' 2>/dev/null || true"

echo ""
echo "Deploy concluído."
echo "  Teste: http://$NIP_DOMAIN"
echo "  Produção (após DNS A financialiq → $SSH_HOST): https://$DEPLOY_DOMAIN"
echo "  Evolution manager (SSH tunnel): ssh -L 8085:127.0.0.1:8085 root@$SSH_HOST"
echo "  Webhook WhatsApp: cd $REMOTE_DIR && sudo -u admin $DEPLOY_PHP_BIN artisan evolution:webhook-sync"
