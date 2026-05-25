#!/usr/bin/env bash
# Instala Docker se faltar e sobe Evolution API no VPS de producao.
# Uso: ./scripts/deploy-evolution-prod.sh [--setup-db]
# Le credenciais SSH e Evolution de .env_deploy na raiz do projeto.
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
DEPLOY_WEB_USER="$(load_var DEPLOY_WEB_USER)"
EVOLUTION_DB_DATABASE="$(load_var EVOLUTION_DB_DATABASE)"
EVOLUTION_DB_USERNAME="$(load_var EVOLUTION_DB_USERNAME)"
EVOLUTION_DB_PASSWORD="$(load_var EVOLUTION_DB_PASSWORD)"

: "${SSH_HOST:?SSH_HOST ausente em $ENV_FILE}"
: "${REMOTE_DIR:?DEPLOY_REMOTE_DIR ausente em $ENV_FILE}"

SSH_PORT="${SSH_PORT:-22}"
SSH_USER="${SSH_USER:-root}"
DEPLOY_WEB_USER="${DEPLOY_WEB_USER:-admin}"
EVOLUTION_DB_DATABASE="${EVOLUTION_DB_DATABASE:-${DEPLOY_WEB_USER}_evolution}"
EVOLUTION_DB_USERNAME="${EVOLUTION_DB_USERNAME:-${DEPLOY_WEB_USER}_evolution}"
SETUP_DB=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    --setup-db)
      SETUP_DB=true
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

echo "==> Instalar Docker e Docker Compose no servidor, se necessario"
RUN 'bash -s' <<'REMOTE_DOCKER'
set -euo pipefail
if ! command -v docker >/dev/null 2>&1; then
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y docker.io
  systemctl enable --now docker
fi

if ! docker compose version >/dev/null 2>&1 && ! command -v docker-compose >/dev/null 2>&1; then
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y docker-compose-plugin 2>/dev/null \
    || DEBIAN_FRONTEND=noninteractive apt-get install -y docker-compose
fi
REMOTE_DOCKER

echo "==> Enviar docker-compose.production.yml e patch SQL"
RUN "mkdir -p '$REMOTE_DIR/docker/mysql'"
rsync -avz -e "$RSYNC_RSH" "$ROOT/docker-compose.production.yml" "$SSH_USER@$SSH_HOST:$REMOTE_DIR/docker-compose.production.yml"
rsync -avz -e "$RSYNC_RSH" "$ROOT/docker/mysql/fix-evolution-schema.sql" "$SSH_USER@$SSH_HOST:$REMOTE_DIR/docker/mysql/fix-evolution-schema.sql"

echo "==> Garantir variaveis Evolution no .env remoto"
RUN "REMOTE_DIR='$REMOTE_DIR' DEFAULT_EVO_DB='$EVOLUTION_DB_DATABASE' DEFAULT_EVO_USER='$EVOLUTION_DB_USERNAME' bash -s" <<'REMOTE_ENV'
set -euo pipefail
cd "$REMOTE_DIR"
touch .env

existing_value() {
  local key="$1"
  grep "^${key}=" .env 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d '"' || true
}

secret_value() {
  local key="$1"
  grep "^${key}=" .deploy-secrets 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d '"' || true
}

upsert_env() {
  local key="$1"
  local value="$2"
  [[ -n "$value" ]] || return 0
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

EVO_DB="$(existing_value EVOLUTION_DB_DATABASE)"
EVO_USER="$(existing_value EVOLUTION_DB_USERNAME)"
EVO_PASS="$(existing_value EVOLUTION_DB_PASSWORD)"
EVO_PASS="${EVO_PASS:-$(secret_value EVOLUTION_DB_PASSWORD)}"

upsert_env EVOLUTION_DB_DATABASE "${EVO_DB:-$DEFAULT_EVO_DB}"
upsert_env EVOLUTION_DB_USERNAME "${EVO_USER:-$DEFAULT_EVO_USER}"
upsert_env EVOLUTION_DB_PASSWORD "$EVO_PASS"

if [[ -z "$(existing_value EVOLUTION_DB_DATABASE)" || -z "$(existing_value EVOLUTION_DB_USERNAME)" || -z "$(existing_value EVOLUTION_DB_PASSWORD)" ]]; then
  echo "Variaveis EVOLUTION_DB_* incompletas no .env remoto." >&2
  exit 1
fi
REMOTE_ENV

if [[ "$SETUP_DB" == true ]]; then
  echo "==> Criar banco Evolution no MariaDB"
  if [[ -z "${EVOLUTION_DB_PASSWORD:-}" ]]; then
    echo "EVOLUTION_DB_PASSWORD vazio; preencha .env_deploy antes de usar --setup-db" >&2
    exit 1
  fi

  RUN "mysql -e \"
    CREATE DATABASE IF NOT EXISTS \\\`${EVOLUTION_DB_DATABASE}\\\`
      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS '${EVOLUTION_DB_USERNAME}'@'localhost'
      IDENTIFIED BY '${EVOLUTION_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \\\`${EVOLUTION_DB_DATABASE}\\\`.*
      TO '${EVOLUTION_DB_USERNAME}'@'localhost';
    FLUSH PRIVILEGES;
  \""
fi

echo "==> Aplicar patch de schema Evolution"
RUN "REMOTE_DIR='$REMOTE_DIR' DEFAULT_EVO_DB='$EVOLUTION_DB_DATABASE' bash -s" <<'REMOTE_PATCH'
set -euo pipefail
cd "$REMOTE_DIR"
test -f docker/mysql/fix-evolution-schema.sql

DB_NAME="$(grep '^EVOLUTION_DB_DATABASE=' .env 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d '"' || true)"
DB_NAME="${DB_NAME:-$DEFAULT_EVO_DB}"
if [[ -z "$DB_NAME" ]]; then
  echo "EVOLUTION_DB_DATABASE ausente no .env remoto e sem default de deploy." >&2
  exit 1
fi

DB_CLIENT="$(command -v mariadb || command -v mysql || true)"
if [[ -z "$DB_CLIENT" ]]; then
  echo "Cliente MariaDB/MySQL nao encontrado no servidor." >&2
  exit 1
fi

"$DB_CLIENT" "$DB_NAME" < docker/mysql/fix-evolution-schema.sql
REMOTE_PATCH

echo "==> Subir Evolution API (porta 8085, network_mode: host)"
RUN "cd '$REMOTE_DIR' && if docker compose version >/dev/null 2>&1; then docker compose -f docker-compose.production.yml --env-file .env up -d evolution-api; else docker-compose -f docker-compose.production.yml --env-file .env up -d evolution-api; fi"

echo "==> Aguardar API..."
RUN "bash -s" <<REMOTE_HEALTH
set -euo pipefail
API_KEY="\$(grep '^EVOLUTION_API_KEY=' '$REMOTE_DIR/.env' | cut -d= -f2)"
for i in {1..12}; do
  STATUS="\$(curl -sf -o /dev/null -w '%{http_code}' -H "apikey: \$API_KEY" http://127.0.0.1:8085/ 2>/dev/null || true)"
  if [[ "\$STATUS" == "200" || "\$STATUS" == "401" || "\$STATUS" == "404" ]]; then
    echo "Evolution respondeu HTTP \$STATUS"
    exit 0
  fi
  sleep 5
done
echo "Evolution ainda nao respondeu; verifique docker logs evolution-api se necessario."
REMOTE_HEALTH

echo ""
echo "Proximos passos no servidor:"
echo "  1. Manager: ./scripts/evolution-prod-tunnel.sh -> http://127.0.0.1:8085/manager"
echo "  2. Criar instancia financial-system e escanear QR"
echo "  3. cd $REMOTE_DIR && php artisan evolution:webhook-sync"
echo ""
echo "Se wavoipToken faltar: mysql \$EVOLUTION_DB_DATABASE < docker/mysql/fix-evolution-schema.sql"
