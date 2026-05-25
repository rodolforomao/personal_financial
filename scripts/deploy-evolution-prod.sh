#!/usr/bin/env bash
# Instala Docker (se faltar) e sobe Evolution API no VPS de produção.
# Uso: ./scripts/deploy-evolution-prod.sh [--setup-db]
# Lê credenciais SSH e Evolution de .env_deploy na raiz do projeto.
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
EVOLUTION_DB_DATABASE="$(load_var EVOLUTION_DB_DATABASE)"
EVOLUTION_DB_USERNAME="$(load_var EVOLUTION_DB_USERNAME)"
EVOLUTION_DB_PASSWORD="$(load_var EVOLUTION_DB_PASSWORD)"

: "${SSH_HOST:?SSH_HOST ausente em $ENV_FILE}"
: "${REMOTE_DIR:?DEPLOY_REMOTE_DIR ausente em $ENV_FILE}"
: "${EVOLUTION_DB_DATABASE:?EVOLUTION_DB_DATABASE ausente em $ENV_FILE}"
: "${EVOLUTION_DB_USERNAME:?EVOLUTION_DB_USERNAME ausente em $ENV_FILE}"

SSH_PORT="${SSH_PORT:-22}"
SSH_USER="${SSH_USER:-root}"

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

echo "==> Instalar Docker no servidor (se necessário)"
RUN 'command -v docker >/dev/null 2>&1 || (
  apt-get update -qq &&
  DEBIAN_FRONTEND=noninteractive apt-get install -y docker.io &&
  systemctl enable --now docker
)'
# docker compose (plugin ou standalone)
RUN 'command -v "docker compose" >/dev/null 2>&1 || command -v docker-compose >/dev/null 2>&1 || (
  DEBIAN_FRONTEND=noninteractive apt-get install -y docker-compose 2>/dev/null || true
)'

echo "==> Enviar docker-compose.production.yml"
RUN "mkdir -p $REMOTE_DIR/docker/mysql"
rsync -avz -e "$RSYNC_RSH" \
  "$ROOT/docker-compose.production.yml" \
  "$ROOT/docker/mysql/fix-evolution-schema.sql" \
  "$SSH_USER@$SSH_HOST:$REMOTE_DIR/"

if [[ "${1:-}" == "--setup-db" ]]; then
  echo "==> Criar banco Evolution no MariaDB (Hestia)"
  if [[ -z "${EVOLUTION_DB_PASSWORD:-}" ]]; then
    echo "EVOLUTION_DB_PASSWORD vazio — crie o banco no HestiaCP e preencha .env_deploy antes de --setup-db" >&2
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

echo "==> Subir Evolution API (porta 8085, network_mode: host)"
RUN "cd $REMOTE_DIR && mysql \$(grep EVOLUTION_DB_DATABASE= .env|cut -d= -f2) < docker/mysql/fix-evolution-schema.sql 2>/dev/null || true"
RUN "cd $REMOTE_DIR && (docker compose -f docker-compose.production.yml --env-file .env up -d evolution-api 2>/dev/null || docker-compose -f docker-compose.production.yml --env-file .env up -d evolution-api)"

echo "==> Aguardar API..."
sleep 15

echo "==> Health check"
RUN "curl -sf -o /dev/null -w '%{http_code}\n' -H \"apikey: \$(grep '^EVOLUTION_API_KEY=' $REMOTE_DIR/.env | cut -d= -f2)\" http://127.0.0.1:8085/ 2>/dev/null || echo 'aguardando...'"

echo ""
echo "Próximos passos no servidor:"
echo "  1. Manager: ssh -L 8085:127.0.0.1:8085 root@$SSH_HOST → http://127.0.0.1:8085/manager"
echo "  2. Criar instância financial-system e escanear QR"
echo "  3. cd $REMOTE_DIR && php artisan evolution:webhook-sync"
echo ""
echo "Se wavoipToken faltar: mysql \$EVOLUTION_DB_DATABASE < docker/mysql/fix-evolution-schema.sql"
