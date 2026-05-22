#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
echo "Aplicando correção wavoipToken no banco evolution..."
docker compose exec -T mysql mysql -uroot -proot < docker/mysql/fix-evolution-schema.sql
docker compose restart evolution-api
echo "Aguarde ~10s e acesse http://127.0.0.1:8081/manager"
