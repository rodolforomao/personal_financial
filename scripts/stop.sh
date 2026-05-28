#!/usr/bin/env bash
# Encerra apenas processos deste projeto (app 8300, Vite 8330).
# Não mata MySQL (3307) nem Redis (6379/6380) — serviços compartilhados entre projetos.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

kill_port() {
  local port="$1"
  local pids
  pids="$(lsof -ti :"$port" 2>/dev/null || true)"
  if [[ -n "$pids" ]]; then
    echo "Encerrando processos na porta ${port}: ${pids}"
    # shellcheck disable=SC2086
    kill $pids || true
  fi
}

kill_port 8300
kill_port 8330

pkill -f "php artisan queue:listen --tries=1 --timeout=0" 2>/dev/null || true
pkill -f "php artisan pail --timeout=0" 2>/dev/null || true

echo "Stop concluido para o projeto (portas 8300 e 8330)."
