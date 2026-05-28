#!/usr/bin/env bash
# Setup local: reutiliza MySQL/Redis compartilhados (portas fixas entre projetos).
# Não altera portas de banco — apenas app/Vite usam faixa 83xx (./scripts/start.sh).
set -euo pipefail

cd "$(dirname "$0")/.."

# Portas compartilhadas entre todos os projetos (não mudar aqui).
MYSQL_PORT=3307
REDIS_PORT=6379
REDIS_DOCKER_HOST_PORT=6380

port_in_use() {
  local port="$1"
  if command -v ss >/dev/null 2>&1; then
    ss -ltn 2>/dev/null | grep -qE ":${port}[[:space:]]"
    return $?
  fi
  if command -v lsof >/dev/null 2>&1; then
    lsof -ti :"$port" >/dev/null 2>&1
    return $?
  fi
  return 1
}

ensure_compose_service() {
  local service="$1"
  local port="$2"
  local label="${3:-$service}"

  if port_in_use "$port"; then
    echo "==> Porta ${port} em uso — reutilizando ${label} compartilhado (não sobe novo container)."
    return 0
  fi

  echo "==> Subindo ${label} via Docker (porta host ${port})..."
  docker compose up -d "$service"
}

load_env_db() {
  if [[ ! -f .env ]]; then
    return
  fi
  if grep -q '^DB_PORT=' .env; then
    MYSQL_PORT="$(grep '^DB_PORT=' .env | cut -d= -f2- | tr -d '"')"
  fi
}

set_env_kv() {
  local key="$1"
  local val="$2"
  if grep -q "^${key}=" .env 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${val}|" .env
  else
    echo "${key}=${val}" >> .env
  fi
}

mysql_ping_from_env() {
  local user pass host
  user="$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')"
  pass="$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')"
  host="$(grep '^DB_HOST=' .env | cut -d= -f2- | tr -d '"' || echo 127.0.0.1)"
  port="$(grep '^DB_PORT=' .env | cut -d= -f2- | tr -d '"' || echo "$MYSQL_PORT")"

  if [[ -z "$user" || -z "$pass" ]]; then
    return 1
  fi

  if command -v mysqladmin >/dev/null 2>&1; then
    mysqladmin ping -h "$host" -P "$port" -u "$user" -p"$pass" --silent 2>/dev/null
    return $?
  fi

  if command -v mysql >/dev/null 2>&1; then
    mysql -h "$host" -P "$port" -u "$user" -p"$pass" -e "SELECT 1" >/dev/null 2>&1
    return $?
  fi

  return 1
}

echo "==> Infra compartilhada: MySQL ${MYSQL_PORT}, Redis ${REDIS_PORT} (ou ${REDIS_DOCKER_HOST_PORT})"
ensure_compose_service mysql "$MYSQL_PORT" "MySQL"
ensure_compose_service redis "$REDIS_DOCKER_HOST_PORT" "Redis (Docker)"

echo "==> Instalando dependências PHP..."
composer install --no-interaction

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --no-interaction
fi

load_env_db

# Só garante host/porta/banco — preserva usuário/senha já definidos no .env.
echo "==> Validando .env (DB em 127.0.0.1:${MYSQL_PORT})..."
set_env_kv DB_CONNECTION mysql
set_env_kv DB_HOST 127.0.0.1
set_env_kv DB_PORT "$MYSQL_PORT"
if ! grep -q '^DB_DATABASE=' .env; then
  set_env_kv DB_DATABASE financial
fi

if port_in_use "$REDIS_PORT"; then
  set_env_kv REDIS_PORT "$REDIS_PORT"
elif port_in_use "$REDIS_DOCKER_HOST_PORT"; then
  set_env_kv REDIS_PORT "$REDIS_DOCKER_HOST_PORT"
fi

if ! mysql_ping_from_env; then
  echo "==> Credenciais do .env não conectaram — provisionando usuário no MySQL compartilhado..."
  bash scripts/provision-mysql-shared.sh
fi

if ! mysql_ping_from_env; then
  echo "ERRO: MySQL não acessível com DB_* do .env em 127.0.0.1:${MYSQL_PORT}."
  echo "  Rode: bash scripts/provision-mysql-shared.sh"
  exit 1
fi

echo "==> Migrando e seed..."
env -u DB_USERNAME -u DB_PASSWORD -u DB_HOST -u DB_PORT -u DB_DATABASE -u DB_CONNECTION \
  php artisan migrate --seed --force

echo ""
echo "Setup concluído."
echo "  Start:   ./scripts/start.sh"
echo "  Stop:    ./scripts/stop.sh"
echo "  API:     http://127.0.0.1:8300"
echo "  Vite:    http://127.0.0.1:8330"
echo "  MySQL:   127.0.0.1:${MYSQL_PORT} (compartilhado)"
echo "  Login:   admin@financial.local / password"
echo "  Header:  X-Workspace-Id: 1"
