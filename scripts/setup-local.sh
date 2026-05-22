#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Subindo MySQL (3307) e Redis (6379) via Docker..."
docker compose up -d mysql redis

echo "==> Aguardando MySQL..."
for i in {1..30}; do
  if docker compose exec -T mysql mysqladmin ping -h localhost -u financial -psecret --silent 2>/dev/null; then
    break
  fi
  sleep 2
done

if ! docker compose exec -T mysql mysqladmin ping -h localhost -u financial -psecret --silent 2>/dev/null; then
  echo "ERRO: MySQL não respondeu. Verifique: docker compose logs mysql"
  exit 1
fi

echo "==> Instalando dependências PHP..."
composer install --no-interaction

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --no-interaction
fi

echo "==> Migrando e seed..."
php artisan migrate --seed --force

echo ""
echo "Setup concluído."
echo "  API:     php artisan serve"
echo "  Filas:   php artisan queue:listen --queue=default,ocr,ai,notifications"
echo "  Login:   admin@financial.local / password"
echo "  Header:  X-Workspace-Id: 1"
