#!/usr/bin/env bash
# Teste rápido da API — rode: bash scripts/test-api.sh
# Pré-requisitos: servidor em BASE_URL + migrate --seed (usuário demo)
set -euo pipefail
cd "$(dirname "$0")/.."

BASE="${BASE_URL:-http://127.0.0.1:8000}"
JSON=(-H "Content-Type: application/json" -H "Accept: application/json")

echo "==> Login"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/api/auth/login" \
  "${JSON[@]}" \
  -d '{"email":"admin@financial.local","password":"password"}')
HTTP_CODE=$(echo "$RESP" | tail -n1)
BODY=$(echo "$RESP" | sed '$d')
RESP="$BODY"

if [ "$HTTP_CODE" != "200" ]; then
  echo "Falha no login (HTTP $HTTP_CODE). Resposta:"
  echo "$RESP" | head -c 800
  echo
  if [ "$HTTP_CODE" = "422" ]; then
    echo "Dica: rode ./scripts/artisan migrate --seed para criar admin@financial.local / password"
  fi
  exit 1
fi

TOKEN=$(echo "$RESP" | php -r 'echo json_decode(file_get_contents("php://stdin"))->token ?? "";')
if [ -z "$TOKEN" ]; then
  echo "Resposta sem token:"
  echo "$RESP" | head -c 500
  exit 1
fi
echo "OK — token obtido"

WORKSPACE_ID=$(echo "$RESP" | php -r '
$j = json_decode(file_get_contents("php://stdin"));
echo $j->user->workspaces[0]->id ?? "";
')
if [ -z "$WORKSPACE_ID" ]; then
  echo "Login OK mas sem workspace no usuário. Rode: ./scripts/artisan migrate --seed"
  exit 1
fi
echo "Workspace ID: $WORKSPACE_ID"

AUTH=(-H "Authorization: Bearer $TOKEN" -H "X-Workspace-Id: $WORKSPACE_ID" "${JSON[@]}")

echo "==> Criar despesa"
curl -s -X POST "$BASE/api/v1/transactions" "${AUTH[@]}" \
  -d '{"type":"expense","amount":49.90,"description":"OpenAI API","transaction_date":"2026-05-21","counterparty":"OpenAI","status":"confirmed"}' | php -r 'echo json_encode(json_decode(file_get_contents("php://stdin")), JSON_PRETTY_PRINT); echo PHP_EOL;'

echo "==> Criar receita"
TX=$(curl -s -X POST "$BASE/api/v1/transactions" "${AUTH[@]}" \
  -d '{"type":"income","amount":15000,"description":"Mensalidade cliente X","transaction_date":"2026-05-21","status":"confirmed"}')
echo "$TX" | php -r 'echo json_encode(json_decode(file_get_contents("php://stdin")), JSON_PRETTY_PRINT); echo PHP_EOL;'
TX_ID=$(echo "$TX" | php -r 'echo json_decode(file_get_contents("php://stdin"))->id ?? "";')

if [ -n "$TX_ID" ]; then
  echo "==> Atualizar transação (PATCH)"
  curl -s -X PATCH "$BASE/api/v1/transactions/$TX_ID" "${AUTH[@]}" \
    -d '{"description":"Mensalidade cliente X (confirmada)"}' | php -r '$j=json_decode(file_get_contents("php://stdin")); echo isset($j->id)?"OK id={$j->id}\n":substr(json_encode($j),0,120)."\n";'
fi

echo "==> Dashboard"
curl -s "$BASE/api/v1/dashboard" -H "Authorization: Bearer $TOKEN" -H "X-Workspace-Id: $WORKSPACE_ID" -H "Accept: application/json" \
  | php -r 'echo json_encode(json_decode(file_get_contents("php://stdin")), JSON_PRETTY_PRINT); echo PHP_EOL;'

echo "==> Listar transações"
curl -s "$BASE/api/v1/transactions" -H "Authorization: Bearer $TOKEN" -H "X-Workspace-Id: $WORKSPACE_ID" -H "Accept: application/json" \
  | php -r '$d=json_decode(file_get_contents("php://stdin")); echo is_array($d)?count($d)." transações\n":substr(json_encode($d),0,200)."\n";'
