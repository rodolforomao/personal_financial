#!/usr/bin/env bash
# Roda testes com MySQL (sem sqlite) — usa credenciais do .env + banco financial_test
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source <(grep -E '^DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME|PASSWORD)=' .env | sed 's/^/export /')
  set +a
fi

export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="financial_test"
# Credenciais do .env (evita DB_USERNAME=sa exportado no shell)
export DB_USERNAME="${DB_USERNAME:-financial}"
export DB_PASSWORD="${DB_PASSWORD:-secret}"

exec env -u DB_USERNAME -u DB_PASSWORD \
  DB_CONNECTION="$DB_CONNECTION" \
  DB_HOST="$DB_HOST" \
  DB_PORT="$DB_PORT" \
  DB_DATABASE="$DB_DATABASE" \
  DB_USERNAME="${DB_USERNAME}" \
  DB_PASSWORD="${DB_PASSWORD}" \
  php artisan test "$@"
