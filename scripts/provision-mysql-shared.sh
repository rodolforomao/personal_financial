#!/usr/bin/env bash
# Cria usuário/bancos do FinancialIQ no MySQL compartilhado da porta 3307.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MYSQL_PORT="${MYSQL_PORT:-3307}"
DB_USER="${DB_USERNAME:-financial}"
DB_PASS="${DB_PASSWORD:-secret}"
DB_NAME="${DB_DATABASE:-financial}"
TEST_DB="${DB_TEST_DATABASE:-financial_test}"

load_env_db() {
  if [[ ! -f .env ]]; then
    return
  fi
  DB_HOST="$(grep '^DB_HOST=' .env | cut -d= -f2- | tr -d '"' || echo 127.0.0.1)"
  MYSQL_PORT="$(grep '^DB_PORT=' .env | cut -d= -f2- | tr -d '"' || echo "$MYSQL_PORT")"
  DB_USER="$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"' || echo "$DB_USER")"
  DB_PASS="$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"' || echo "$DB_PASS")"
  DB_NAME="$(grep '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"' || echo "$DB_NAME")"
}

load_env_db

mysql_container_for_port() {
  docker ps --format '{{.Names}}\t{{.Ports}}' 2>/dev/null \
    | awk -v p=":${MYSQL_PORT}->" '$0 ~ p { print $1; exit }'
}

mysql_try() {
  mysql -h "${DB_HOST:-127.0.0.1}" -P "$MYSQL_PORT" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" >/dev/null 2>&1
}

if mysql_try; then
  echo "MySQL OK: ${DB_USER}@${DB_HOST:-127.0.0.1}:${MYSQL_PORT}"
  exit 0
fi

container="$(mysql_container_for_port || true)"
if [[ -z "$container" ]]; then
  echo "ERRO: porta ${MYSQL_PORT} em uso, mas não achei container Docker mapeado." >&2
  exit 1
fi

root_pass="$(docker inspect "$container" --format '{{range .Config.Env}}{{println .}}{{end}}' \
  | awk -F= '/^MYSQL_ROOT_PASSWORD=/{print $2; exit}')"

if [[ -z "$root_pass" ]]; then
  echo "ERRO: não foi possível ler MYSQL_ROOT_PASSWORD do container ${container}." >&2
  exit 1
fi

echo "==> Provisionando ${DB_USER} e bancos em ${container} (porta host ${MYSQL_PORT})..."

docker exec "$container" mysql -uroot -p"${root_pass}" -e "
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`${TEST_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
GRANT ALL PRIVILEGES ON \`${TEST_DB}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
" >/dev/null

if ! mysql_try; then
  echo "ERRO: provisionamento executado, mas ${DB_USER} ainda não conecta em 127.0.0.1:${MYSQL_PORT}." >&2
  exit 1
fi

echo "Provisionamento concluído: ${DB_USER}@${DB_HOST:-127.0.0.1}:${MYSQL_PORT}"
