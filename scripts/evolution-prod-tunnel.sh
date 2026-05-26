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
  awk -F= -v key="$key" '
    $1 == key {
      value = substr($0, length(key) + 2)
      if (value ~ /^".*"$/) {
        sub(/^"/, "", value)
        sub(/"$/, "", value)
      }
      print value
      exit
    }
  ' "$ENV_FILE"
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

validate_port() {
  local name="$1"
  local value="$2"
  if ! [[ "$value" =~ ^[0-9]+$ ]] || (( value < 1 || value > 65535 )); then
    echo "${name} inválida: ${value}" >&2
    exit 1
  fi
}

validate_port "Porta local" "$LOCAL_PORT"
validate_port "Porta remota" "$REMOTE_PORT"

command -v ssh >/dev/null 2>&1 || { echo "Comando ssh não encontrado." >&2; exit 1; }

SSH_AUTH_PREFIX=()
SSH_BATCH_ARGS=(-o BatchMode=yes)
if [[ -n "${SSH_PASSWORD:-}" ]]; then
  if ! command -v sshpass >/dev/null 2>&1; then
    echo "SSH_PASSWORD está definido em ${ENV_FILE}, mas sshpass não está instalado." >&2
    echo "Instale sshpass ou configure autenticação por chave SSH." >&2
    exit 1
  fi
  SSH_AUTH_PREFIX=(sshpass -p "$SSH_PASSWORD")
  SSH_BATCH_ARGS=()
fi

if command -v ss >/dev/null 2>&1 && ss -tln | grep -q ":${LOCAL_PORT} "; then
  echo "Porta local ${LOCAL_PORT} já está em uso." >&2
  echo "Use outra porta: ./scripts/evolution-prod-tunnel.sh --local-port 18085" >&2
  exit 1
fi

SSH_COMMON_ARGS=(
  -o StrictHostKeyChecking=accept-new
  -o ConnectTimeout=10
  -o ConnectionAttempts=1
  -o ServerAliveInterval=15
  -o ServerAliveCountMax=2
  "${SSH_BATCH_ARGS[@]}"
  -p "$SSH_PORT"
  "$SSH_USER@$SSH_HOST"
)

run_remote_check() {
  "${SSH_AUTH_PREFIX[@]}" ssh "${SSH_COMMON_ARGS[@]}" "$@"
}

echo "Validando acesso SSH em ${SSH_USER}@${SSH_HOST}:${SSH_PORT}..."
if ! ssh_error="$(run_remote_check 'true' 2>&1)"; then
  echo "Não foi possível acessar o servidor remoto via SSH." >&2
  echo "Destino: ${SSH_USER}@${SSH_HOST}:${SSH_PORT}" >&2
  echo "$ssh_error" >&2
  exit 1
fi

remote_port_check="if command -v nc >/dev/null 2>&1; then nc -z 127.0.0.1 ${REMOTE_PORT}; else bash -lc ':</dev/tcp/127.0.0.1/${REMOTE_PORT}'; fi"
echo "Validando Evolution remoto em 127.0.0.1:${REMOTE_PORT}..."
if ! port_error="$(run_remote_check "$remote_port_check" 2>&1)"; then
  echo "SSH conectou, mas a porta remota 127.0.0.1:${REMOTE_PORT} não respondeu no servidor." >&2
  echo "Verifique se o Evolution Manager está rodando nessa porta em ${SSH_HOST}." >&2
  echo "$port_error" >&2
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
  -o ConnectTimeout=10
  -o ConnectionAttempts=1
  -o ServerAliveInterval=15
  -o ServerAliveCountMax=2
  "${SSH_BATCH_ARGS[@]}"
  -N
  -L "${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}"
  -p "$SSH_PORT"
  "$SSH_USER@$SSH_HOST"
)

exec "${SSH_AUTH_PREFIX[@]}" ssh "${SSH_ARGS[@]}"
