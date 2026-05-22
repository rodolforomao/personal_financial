#!/usr/bin/env bash
# Cria banco financial_test (usa credenciais admin do .env ou root interativo)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DB_NAME="financial_test"

if [[ -f .env ]]; then
  DB_HOST=$(grep '^DB_HOST=' .env | cut -d= -f2- | tr -d '"')
  DB_PORT=$(grep '^DB_PORT=' .env | cut -d= -f2- | tr -d '"')
  ADMIN_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"')
  ADMIN_PASS=$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

echo "Criando banco ${DB_NAME} em ${DB_HOST}:${DB_PORT}..."

mysql -h "$DB_HOST" -P "$DB_PORT" -u "${ADMIN_USER:-root}" -p"${ADMIN_PASS}" -e \
  "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "OK: ${DB_NAME} pronto. Rode: ./scripts/test.sh"
