#!/usr/bin/env bash
# Sobe ambiente de desenvolvimento deste projeto (portas app 83xx).
# MySQL 3307 e Redis 6379/6380 não são alterados — compartilhados entre projetos.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

APP_PORT="${APP_PORT:-8300}"
VITE_PORT="${VITE_PORT:-8330}"
APP_HOST="${APP_HOST:-127.0.0.1}"

if [ ! -f .env ]; then
  echo "Arquivo .env ausente. Rode antes: ./scripts/setup-local.sh"
  exit 1
fi

if ! command -v npx >/dev/null 2>&1; then
  echo "npx não encontrado. Rode: npm install"
  exit 1
fi

echo "Iniciando FinancialIQ em http://${APP_HOST}:${APP_PORT} (Vite: ${VITE_PORT})..."

export COMPOSER_DISABLE_XDEBUG_WARN=1

if ! php artisan rbac:sync --no-ansi 2>/dev/null; then
  echo "Aviso: não foi possível sincronizar RBAC (verifique MySQL e rode: php artisan migrate --seed)"
fi

exec npx concurrently \
  -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" \
  "php artisan serve --host=${APP_HOST} --port=${APP_PORT}" \
  "php artisan queue:listen --tries=1 --timeout=0" \
  "php artisan pail --timeout=0" \
  "npm run dev -- --host ${APP_HOST} --port ${VITE_PORT} --strictPort" \
  --names=server,queue,logs,vite \
  --kill-others
