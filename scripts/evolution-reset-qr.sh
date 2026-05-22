#!/usr/bin/env bash
# Reinicia Evolution com volume limpo para forçar novo QR (use se o manager ficar sem QR)
set -euo pipefail
cd "$(dirname "$0")/.."

echo "Parando evolution-api..."
docker compose stop evolution-api

echo "Removendo volume de sessões (instances)..."
docker volume rm financial_project_evolution_instances 2>/dev/null || true

echo "Garantindo coluna wavoipToken..."
docker compose exec -T mysql mysql -uroot -proot -e \
  "CREATE DATABASE IF NOT EXISTS evolution; USE evolution; ALTER TABLE Setting ADD COLUMN IF NOT EXISTS wavoipToken VARCHAR(100) NULL;" 2>/dev/null || true

echo "Subindo evolution-api (imagem homolog + cache local)..."
docker compose up -d evolution-api

echo "Aguardando API (30s)..."
sleep 30

APIKEY=$(grep '^EVOLUTION_API_KEY=' .env | cut -d= -f2)
echo "Criando instância financial-system..."
curl -s -X POST "http://127.0.0.1:8081/instance/create" \
  -H "apikey: ${APIKEY}" \
  -H "Content-Type: application/json" \
  -d '{"instanceName":"financial-system","integration":"WHATSAPP-BAILEYS","qrcode":true}' | head -c 400
echo ""

echo "Solicitando QR (connect)..."
for i in 1 2 3; do
  R=$(curl -s -m 30 "http://127.0.0.1:8081/instance/connect/financial-system" -H "apikey: ${APIKEY}")
  echo "tentativa $i: $R" | head -c 200
  echo ""
  if echo "$R" | grep -q base64; then
    echo "QR gerado. Abra http://127.0.0.1:8081/manager e conecte."
    exit 0
  fi
  sleep 5
done

echo "QR ainda vazio. Abra o manager, delete a instância antiga (UUID) e use financial-system → Connect."
echo "Logs: docker compose logs evolution-api --tail 50"
