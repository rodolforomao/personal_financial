#!/usr/bin/env bash
# Deploy Financial Intelligence -> financialiq.rodolforomao.com.br (Ultrahost / HestiaCP)
# Isolado: nao altera outros sites, pools PHP existentes nem bancos de outros projetos.
#
# Uso:
#   ./scripts/deploy-financialiq.sh [--skip-build] [--skip-evolution]
#   ./scripts/deploy-financialiq.sh --restore-db [--backup storage/backups/arquivo.sql.gz] [--restore-storage]
#
# Deploy normal NUNCA restaura banco nem storage. Restore e destrutivo e precisa ser opt-in.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${DEPLOY_ENV_FILE:-$ROOT/.env_deploy}"
[[ -f "$ENV_FILE" ]] || { echo "Arquivo nao encontrado: $ENV_FILE" >&2; exit 1; }

load_var() {
  local key="$1"
  local line
  line="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%\"}"
  line="${line#\"}"
  printf '%s' "$line"
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

: "${SSH_HOST:?SSH_HOST ausente em $ENV_FILE}"
: "${REMOTE_DIR:?DEPLOY_REMOTE_DIR ausente em $ENV_FILE}"
: "${DEPLOY_DOMAIN:?DEPLOY_DOMAIN ausente em $ENV_FILE}"

SSH_PORT="${SSH_PORT:-22}"
SSH_USER="${SSH_USER:-root}"
DEPLOY_PHP_BIN="${DEPLOY_PHP_BIN:-/usr/bin/php8.4}"
DEPLOY_WEB_USER="${DEPLOY_WEB_USER:-admin}"
PHP_VERSION="${DEPLOY_PHP_BIN##*php}"
[[ "$PHP_VERSION" == "$DEPLOY_PHP_BIN" || -z "$PHP_VERSION" ]] && PHP_VERSION="8.4"
NIP_DOMAIN="financialiq.${SERVER_IP}.nip.io"

SKIP_BUILD=false
SKIP_EVOLUTION=false
RESTORE_DB=false
RESTORE_STORAGE=false
BACKUP_FILE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-build)
      SKIP_BUILD=true
      shift
      ;;
    --skip-evolution)
      SKIP_EVOLUTION=true
      shift
      ;;
    --restore-db)
      RESTORE_DB=true
      shift
      ;;
    --restore-storage)
      RESTORE_STORAGE=true
      shift
      ;;
    --backup)
      BACKUP_FILE="${2:-}"
      [[ -n "$BACKUP_FILE" ]] || { echo "--backup precisa de um arquivo .sql.gz" >&2; exit 1; }
      shift 2
      ;;
    --skip-db-restore)
      echo "Aviso: --skip-db-restore nao e mais necessario; restore agora so roda com --restore-db."
      shift
      ;;
    *)
      echo "Argumento desconhecido: $1" >&2
      exit 1
      ;;
  esac
done

RUN() {
  if [[ -n "${SSH_PASSWORD:-}" ]] && command -v sshpass &>/dev/null; then
    SSHPASS="$SSH_PASSWORD" sshpass -e ssh -o StrictHostKeyChecking=accept-new -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "$@"
  else
    ssh -o StrictHostKeyChecking=accept-new -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "$@"
  fi
}

RSYNC_RSH="ssh -o StrictHostKeyChecking=accept-new -p $SSH_PORT"
if [[ -n "${SSH_PASSWORD:-}" ]] && command -v sshpass &>/dev/null; then
  export SSHPASS="$SSH_PASSWORD"
  RSYNC_RSH="sshpass -e ssh -o StrictHostKeyChecking=accept-new -p $SSH_PORT"
fi

echo "==> Fase 1: infra isolada no Hestia (somente $DEPLOY_DOMAIN)"
RUN "bash -s" <<REMOTE_SETUP
set -euo pipefail
DOMAIN='$DEPLOY_DOMAIN'
NIP='$NIP_DOMAIN'
IP='$SERVER_IP'
REMOTE='$REMOTE_DIR'
WEBUSER='$DEPLOY_WEB_USER'
PHPVER='$PHP_VERSION'

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

if ! grep -q "DOMAIN='\$DOMAIN'" /usr/local/hestia/data/users/\$WEBUSER/web.conf 2>/dev/null; then
  /usr/local/hestia/bin/v-add-web-domain \$WEBUSER "\$DOMAIN" "\$IP"
fi

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

/usr/local/hestia/bin/v-change-web-domain-backend-tpl \$WEBUSER "\$DOMAIN" PHP-8_4 2>/dev/null || true
/usr/local/hestia/bin/v-change-web-domain-backend-tpl \$WEBUSER "\$NIP" PHP-8_4 2>/dev/null || true

for POOL in /etc/php/\${PHPVER}/fpm/pool.d/\${DOMAIN}.conf /etc/php/\${PHPVER}/fpm/pool.d/\${NIP}.conf; do
  [[ -f "\$POOL" ]] || continue
  if ! grep -q "\$REMOTE" "\$POOL"; then
    sed -i "s|public_shtml:/home/\$WEBUSER/tmp|public_shtml:\$REMOTE:/home/\$WEBUSER/tmp|g" "\$POOL"
  fi
done
systemctl reload php\${PHPVER}-fpm

FIN_DB="\${WEBUSER}_financialiq"
EVO_DB="\${WEBUSER}_evolution"
if ! mysql -e "SHOW DATABASES LIKE '\$FIN_DB';" | grep -q "\$FIN_DB"; then
  /usr/local/hestia/bin/v-add-database \$WEBUSER financialiq financialiq "\$DB_PASS" mysql localhost utf8mb4
fi
if ! mysql -e "SHOW DATABASES LIKE '\$EVO_DB';" | grep -q "\$EVO_DB"; then
  /usr/local/hestia/bin/v-add-database \$WEBUSER evolution evolution "\$EVO_PASS" mysql localhost utf8mb4
fi

command -v tesseract >/dev/null 2>&1 || DEBIAN_FRONTEND=noninteractive apt-get install -y tesseract-ocr tesseract-ocr-por poppler-utils
command -v ffmpeg   >/dev/null 2>&1 || DEBIAN_FRONTEND=noninteractive apt-get install -y ffmpeg
command -v python3  >/dev/null 2>&1 || DEBIAN_FRONTEND=noninteractive apt-get install -y python3 python3-pip
command -v curl     >/dev/null 2>&1 || DEBIAN_FRONTEND=noninteractive apt-get install -y curl
command -v unzip    >/dev/null 2>&1 || DEBIAN_FRONTEND=noninteractive apt-get install -y unzip

# pacote vosk (STT offline para transcricao de audio)
if ! python3 -c "import vosk" 2>/dev/null; then
  echo "    Instalando vosk..."
  python3 -m pip install --quiet vosk 2>/dev/null \
    || python3 -m pip install --quiet --break-system-packages vosk
else
  echo "    vosk ja instalado, pulando."
fi

# modelo Vosk Portugues (persiste entre deploys via exclude no rsync)
VOSK_MODEL="\$REMOTE/scripts/vosk-model-pt"
mkdir -p "\$REMOTE/scripts"
if [[ ! -d "\$VOSK_MODEL" ]]; then
  echo "    Baixando modelo Vosk Portugues (~33MB)..."
  VOSK_TMP=\$(mktemp --suffix=.zip)
  curl -fsSL "https://alphacephei.com/vosk/models/vosk-model-small-pt-0.3.zip" -o "\$VOSK_TMP"
  unzip -q "\$VOSK_TMP" -d "\$REMOTE/scripts"
  mv "\$REMOTE/scripts/vosk-model-small-pt-0.3" "\$VOSK_MODEL"
  rm -f "\$VOSK_TMP"
  chown -R \$WEBUSER:\$WEBUSER "\$VOSK_MODEL"
  echo "    Modelo Vosk instalado em \$VOSK_MODEL"
else
  echo "    Modelo Vosk ja instalado, pulando."
fi

mkdir -p \
  "\$REMOTE/storage/app/private" \
  "\$REMOTE/storage/app/public" \
  "\$REMOTE/storage/framework/cache/data" \
  "\$REMOTE/storage/framework/sessions" \
  "\$REMOTE/storage/framework/views" \
  "\$REMOTE/storage/logs" \
  "\$REMOTE/bootstrap/cache"

chown -R \$WEBUSER:\$WEBUSER "\$REMOTE"
chmod 600 "\$SECRETS"
REMOTE_SETUP

echo "==> Fase 2: build assets local sem lifecycle scripts"
if [[ "$SKIP_BUILD" == false ]]; then
  if [[ -f package-lock.json ]]; then
    npm ci --ignore-scripts --silent
  else
    npm install --ignore-scripts --silent
  fi
  npm run build
fi

echo "==> Fase 3: rsync codigo preservando dados de producao"
RUN "mkdir -p '$REMOTE_DIR' && chown -R '$DEPLOY_WEB_USER:$DEPLOY_WEB_USER' '$REMOTE_DIR'"
rsync -avz --delete -e "$RSYNC_RSH" \
  --exclude node_modules \
  --exclude .git \
  --exclude .env \
  --exclude .env_deploy \
  --exclude vendor \
  --exclude .deploy-secrets \
  --exclude 'bootstrap/cache/*.php' \
  --exclude 'storage/app/*' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  --exclude 'storage/backups/*' \
  --exclude 'scripts/vosk-model-pt' \
  "$ROOT/" "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"

echo "==> Fase 4: .env producao no servidor"
DB_PASS=$(RUN "grep DB_PASSWORD= '$REMOTE_DIR/.deploy-secrets' 2>/dev/null | cut -d= -f2" || true)
EVO_PASS=$(RUN "grep EVOLUTION_DB_PASSWORD= '$REMOTE_DIR/.deploy-secrets' 2>/dev/null | cut -d= -f2" || true)
ENV_TMP=$(mktemp)
SEEN_DB_PASSWORD=false
SEEN_CURL_CA_BUNDLE=false
SEEN_HTTP_FORCE_IPV4=false
SEEN_EVO_DATABASE=false
SEEN_EVO_USERNAME=false
SEEN_EVO_PASSWORD=false
while IFS= read -r line || [[ -n "$line" ]]; do
  if [[ -z "$line" || "$line" =~ ^# ]]; then
    echo "$line"
    continue
  fi

  key="${line%%=*}"
  case "$key" in
    SSH_*|HESTIA_*|DEPLOY_*|EVOLUTION_DOCKER*|EVOLUTION_CONTAINER*)
      continue
      ;;
    DB_PASSWORD)
      SEEN_DB_PASSWORD=true
      echo "DB_PASSWORD=${DB_PASS:-${line#*=}}"
      ;;
    CURL_CA_BUNDLE)
      SEEN_CURL_CA_BUNDLE=true
      echo "CURL_CA_BUNDLE=$REMOTE_DIR/storage/app/ca-certificates.crt"
      ;;
    HTTP_FORCE_IPV4)
      SEEN_HTTP_FORCE_IPV4=true
      echo "HTTP_FORCE_IPV4=true"
      ;;
    EVOLUTION_DB_DATABASE)
      SEEN_EVO_DATABASE=true
      echo "$line"
      ;;
    EVOLUTION_DB_USERNAME)
      SEEN_EVO_USERNAME=true
      echo "$line"
      ;;
    EVOLUTION_DB_PASSWORD)
      SEEN_EVO_PASSWORD=true
      echo "EVOLUTION_DB_PASSWORD=${EVO_PASS:-${line#*=}}"
      ;;
    *)
      echo "$line"
      ;;
  esac
done < "$ENV_FILE" > "$ENV_TMP"
if [[ "$SEEN_DB_PASSWORD" == false && -n "${DB_PASS:-}" ]]; then
  echo "DB_PASSWORD=$DB_PASS" >> "$ENV_TMP"
fi
if [[ "$SEEN_CURL_CA_BUNDLE" == false ]]; then
  echo "CURL_CA_BUNDLE=$REMOTE_DIR/storage/app/ca-certificates.crt" >> "$ENV_TMP"
fi
if [[ "$SEEN_HTTP_FORCE_IPV4" == false ]]; then
  echo "HTTP_FORCE_IPV4=true" >> "$ENV_TMP"
fi
if [[ "$SEEN_EVO_DATABASE" == false ]]; then
  echo "EVOLUTION_DB_DATABASE=${DEPLOY_WEB_USER}_evolution" >> "$ENV_TMP"
fi
if [[ "$SEEN_EVO_USERNAME" == false ]]; then
  echo "EVOLUTION_DB_USERNAME=${DEPLOY_WEB_USER}_evolution" >> "$ENV_TMP"
fi
if [[ "$SEEN_EVO_PASSWORD" == false && -n "${EVO_PASS:-}" ]]; then
  echo "EVOLUTION_DB_PASSWORD=$EVO_PASS" >> "$ENV_TMP"
fi
rsync -avz -e "$RSYNC_RSH" "$ENV_TMP" "$SSH_USER@$SSH_HOST:$REMOTE_DIR/.env"
rm -f "$ENV_TMP"
RUN "set -e; mkdir -p '$REMOTE_DIR/storage/app'; if [[ -f /etc/ssl/certs/ca-certificates.crt ]]; then cp /etc/ssl/certs/ca-certificates.crt '$REMOTE_DIR/storage/app/ca-certificates.crt'; chown '$DEPLOY_WEB_USER:$DEPLOY_WEB_USER' '$REMOTE_DIR/storage/app/ca-certificates.crt'; chmod 644 '$REMOTE_DIR/storage/app/ca-certificates.crt'; fi; chown '$DEPLOY_WEB_USER:$DEPLOY_WEB_USER' '$REMOTE_DIR/.env' && chmod 600 '$REMOTE_DIR/.env'"

echo "==> Fase 5: composer e limpeza de caches"
RUN "REMOTE_DIR='$REMOTE_DIR' DEPLOY_PHP_BIN='$DEPLOY_PHP_BIN' DEPLOY_WEB_USER='$DEPLOY_WEB_USER' bash -s" <<'REMOTE_COMPOSER'
set -euo pipefail
cd "$REMOTE_DIR"
chown -R "$DEPLOY_WEB_USER:$DEPLOY_WEB_USER" .
sudo -u "$DEPLOY_WEB_USER" "$DEPLOY_PHP_BIN" /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
sudo -u "$DEPLOY_WEB_USER" "$DEPLOY_PHP_BIN" artisan optimize:clear
sudo -u "$DEPLOY_WEB_USER" "$DEPLOY_PHP_BIN" artisan storage:link || true
REMOTE_COMPOSER

if [[ "$RESTORE_DB" == true ]]; then
  if [[ -z "$BACKUP_FILE" ]]; then
    BACKUP_FILE="$(ls -t "$ROOT"/storage/backups/*.sql.gz 2>/dev/null | head -1 || true)"
  fi
  [[ -n "$BACKUP_FILE" && -f "$BACKUP_FILE" ]] || { echo "Backup .sql.gz nao encontrado para --restore-db" >&2; exit 1; }
  echo "==> Fase 6: RESTORE DE BANCO solicitado ($BACKUP_FILE)"
  echo "    Atencao: isso substitui dados atuais do banco configurado em DB_DATABASE."
  rsync -avz -e "$RSYNC_RSH" "$BACKUP_FILE" "$SSH_USER@$SSH_HOST:/tmp/financial_restore.sql.gz"
  RUN "DB_NAME=\$(grep '^DB_DATABASE=' '$REMOTE_DIR/.env' | cut -d= -f2); gunzip -c /tmp/financial_restore.sql.gz | mysql \"\$DB_NAME\" && rm -f /tmp/financial_restore.sql.gz"
else
  echo "==> Fase 6: restore de banco ignorado (padrao seguro)"
fi

if [[ "$RESTORE_STORAGE" == true ]]; then
  echo "==> Fase 7: restore de storage/app/private solicitado"
  tar -czf /tmp/financial_storage_private.tar.gz -C "$ROOT/storage/app" private 2>/dev/null || true
  if [[ -f /tmp/financial_storage_private.tar.gz ]]; then
    rsync -avz -e "$RSYNC_RSH" /tmp/financial_storage_private.tar.gz "$SSH_USER@$SSH_HOST:/tmp/"
    RUN "mkdir -p '$REMOTE_DIR/storage/app' && tar -xzf /tmp/financial_storage_private.tar.gz -C '$REMOTE_DIR/storage/app' && rm -f /tmp/financial_storage_private.tar.gz && chown -R '$DEPLOY_WEB_USER:$DEPLOY_WEB_USER' '$REMOTE_DIR/storage'"
    rm -f /tmp/financial_storage_private.tar.gz
  else
    echo "storage/app/private local nao encontrado; pulando restore de storage."
  fi
else
  echo "==> Fase 7: restore de storage ignorado (padrao seguro)"
fi

echo "==> Fase 8: migrations, seeders idempotentes e cache"
RUN "REMOTE_DIR='$REMOTE_DIR' DEPLOY_PHP_BIN='$DEPLOY_PHP_BIN' DEPLOY_WEB_USER='$DEPLOY_WEB_USER' PHP_VERSION='$PHP_VERSION' bash -s" <<'REMOTE_ARTISAN'
set -euo pipefail
cd "$REMOTE_DIR"
sudo -u "$DEPLOY_WEB_USER" "$DEPLOY_PHP_BIN" artisan migrate --force
sudo -u "$DEPLOY_WEB_USER" "$DEPLOY_PHP_BIN" artisan db:seed --class=Database\\Seeders\\FinancialPlatformSeeder --force

DB_NAME="$(grep '^DB_DATABASE=' "$REMOTE_DIR/.env" | cut -d= -f2 | tr -d '\"')"

# Rotas esperadas segundo o seeder (apenas strings literais, ex: 'dashboard', 'reports.index')
EXPECTED=$(grep "'route'" "$REMOTE_DIR/database/seeders/NavigationMenuSeeder.php" | \
  sed -n "s/.*'route'[[:space:]]*=>[[:space:]]*'\([a-z][a-z0-9._-]*\)'.*/\1/p" | sort)
# Rotas que já existem no banco
ACTUAL=$(mysql -N "$DB_NAME" -e \
  "SELECT route FROM navigation_menu_items WHERE type='link' AND route IS NOT NULL ORDER BY route;" \
  2>/dev/null || echo "")

if [[ "$EXPECTED" != "$ACTUAL" ]]; then
  echo "    NavigationMenuSeeder: menu desatualizado, sincronizando..."
  sudo -u "$DEPLOY_WEB_USER" "$DEPLOY_PHP_BIN" artisan db:seed --class=Database\\Seeders\\NavigationMenuSeeder --force
else
  echo "    NavigationMenuSeeder: menu ja sincronizado, ignorando."
fi
"$DEPLOY_PHP_BIN" artisan platform:ensure-health --fix --probe || true
chown -R "$DEPLOY_WEB_USER:$DEPLOY_WEB_USER" storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
sudo -u "$DEPLOY_WEB_USER" "$DEPLOY_PHP_BIN" artisan optimize
systemctl reload php${PHP_VERSION}-fpm 2>/dev/null || true
REMOTE_ARTISAN

echo "==> Fase 9: systemd (somente financial-*)"
RUN "REMOTE_DIR='$REMOTE_DIR' DEPLOY_PHP_BIN='$DEPLOY_PHP_BIN' DEPLOY_WEB_USER='$DEPLOY_WEB_USER' bash -s" <<'REMOTE_SERVICES'
set -euo pipefail
REMOTE="$REMOTE_DIR"
PHP="$DEPLOY_PHP_BIN"
WEBUSER="$DEPLOY_WEB_USER"

cat > /etc/systemd/system/financial-queue.service <<EOF
[Unit]
Description=Financial IQ Queue Worker
After=network.target mariadb.service redis-server.service

[Service]
Type=simple
User=$WEBUSER
Group=$WEBUSER
WorkingDirectory=$REMOTE
ExecStart=$PHP $REMOTE/artisan queue:listen redis --sleep=3 --tries=3 --timeout=0 --queue=notifications,default,ocr,ai
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
User=$WEBUSER
Group=$WEBUSER
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
  echo "==> Fase 10: Evolution Docker (porta 8085; 8081 e reservado pelo Hestia)"
  DEPLOY_ENV_FILE="$ENV_FILE" "$ROOT/scripts/deploy-evolution-prod.sh"
fi

echo "==> Fase 11: SSL nip.io (teste imediato)"
RUN "/usr/local/hestia/bin/v-add-letsencrypt-domain '$DEPLOY_WEB_USER' '$NIP_DOMAIN' '' '' 2>/dev/null || true; /usr/local/hestia/bin/v-add-web-domain-ssl-force '$DEPLOY_WEB_USER' '$NIP_DOMAIN' 2>/dev/null || true"

echo ""
echo "Deploy concluido."
echo "  Teste: http://$NIP_DOMAIN"
echo "  Producao (apos DNS A financialiq -> $SSH_HOST): https://$DEPLOY_DOMAIN"
echo "  Evolution manager (SSH tunnel): ./scripts/evolution-prod-tunnel.sh"
echo "  Webhook WhatsApp: cd $REMOTE_DIR && sudo -u $DEPLOY_WEB_USER $DEPLOY_PHP_BIN artisan evolution:webhook-sync"
