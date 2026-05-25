#!/usr/bin/env bash
# Abre túnel SSH local para acessar o Evolution Manager em produção.
# Uso:
#   ./scripts/evolution-prod-tunnel.sh
#   ./scripts/evolution-prod-tunnel.sh --local-port 18085
#
# Depois abra no navegador local:
#   http://127.0.0.1:8085/manager
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
LOCAL_PORT="$(load_var EVOLUTION_TUNNEL_LOCAL_PORT)"
REMOTE_PORT="$(load_var EVOLUTION_TUNNEL_REMOTE_PORT)"

SSH_USER="${SSH_USER:-root}"
SSH_PORT="${SSH_PORT:-22}"
LOCAL_PORT="${LOCAL_PORT:-8085}"
REMOTE_PORT="${REMOTE_PORT:-8085}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --local-port)
      LOCAL_PORT="${2:?Informe a porta local}"
      shift 2
      ;;
    --remote-port)
      REMOTE_PORT="${2:?Informe a porta remota}"
      shift 2
      ;;
    -h|--help)
      sed -n '1,12p' "$0"
      exit 0
      ;;
    *)
      echo "Opção desconhecida: $1" >&2
      exit 1
      ;;
  esac
done

: "${SSH_HOST:?SSH_HOST ausente em $ENV_FILE}"

if command -v ss >/dev/null 2>&1 && ss -tln | grep -q ":${LOCAL_PORT} "; then
  echo "Porta local ${LOCAL_PORT} já está em uso." >&2
  echo "Use outra porta: ./scripts/evolution-prod-tunnel.sh --local-port 18085" >&2
  exit 1
fi

echo "Abrindo túnel para Evolution produção..."
echo "  Local:  http://127.0.0.1:${LOCAL_PORT}/manager"
echo "  Remoto: 127.0.0.1:${REMOTE_PORT} em ${SSH_USER}@${SSH_HOST}"
echo ""
echo "Mantenha este terminal aberto enquanto usa o manager."
echo "Para encerrar: Ctrl+C"
echo ""

SSH_ARGS=(
  -o StrictHostKeyChecking=accept-new
  -o ExitOnForwardFailure=yes
  -N
  -L "${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}"
  -p "$SSH_PORT"
  "$SSH_USER@$SSH_HOST"
)

if [[ -n "${SSH_PASSWORD:-}" ]] && command -v sshpass >/dev/null 2>&1; then
  exec sshpass -p "$SSH_PASSWORD" ssh "${SSH_ARGS[@]}"
fi

exec ssh "${SSH_ARGS[@]}"
