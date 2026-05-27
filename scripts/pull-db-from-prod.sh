#!/usr/bin/env bash
# Baixa dump do MySQL de produção (via SSH) e restaura no banco local do .env.
# Se o banco local não existir, cria-o. Se existir, apaga e substitui pelos dados de produção.
#
# Pré-requisitos:
#   - .env com credenciais locais (DB_*)
#   - .env_deploy com SSH_HOST, DEPLOY_REMOTE_DIR, etc. (mesmo do deploy)
#   - MySQL local acessível; utilizeador local com permissão ou MYSQL_ADMIN_* para criar DB
#
# Uso:
#   ./scripts/pull-db-from-prod.sh [--yes] [--keep-dump]
#
# Opções:
#   --yes        pula confirmação interativa
#   --keep-dump  mantém cópia em storage/backups/ (além do restore)
set -euo pipefail
set +H 2>/dev/null || true

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${DEPLOY_ENV_FILE:-$ROOT/.env_deploy}"
SKIP_CONFIRM=false
KEEP_DUMP=false
CURRENT_PHASE=""
PROGRESS_PID=""
PROGRESS_FLAG=""

fail() {
  local code="${1:-1}"
  shift || true
  echo "" >&2
  if [[ -n "$CURRENT_PHASE" ]]; then
    echo "ERRO [${CURRENT_PHASE}]: $*" >&2
  else
    echo "ERRO: $*" >&2
  fi
  exit "$code"
}

warn() {
  echo "AVISO: $*" >&2
}

step() {
  CURRENT_PHASE="$1"
  echo ""
  echo "==> ${CURRENT_PHASE}"
}

show_file_errors() {
  local label="$1"
  local err_file="$2"
  if [[ -f "$err_file" && -s "$err_file" ]]; then
    echo "    Detalhes (${label}):" >&2
    sed 's/^/      /' "$err_file" >&2
  fi
}

run_mysql() {
  mysql --defaults-file="$1" "${@:2}" 2>&1
}

cleanup() {
  if [[ -n "${PROGRESS_PID:-}" ]] && kill -0 "$PROGRESS_PID" 2>/dev/null; then
    kill "$PROGRESS_PID" 2>/dev/null || true
    wait "$PROGRESS_PID" 2>/dev/null || true
  fi
  rm -f "$PROGRESS_FLAG" "$MY_CNF" "$ADMIN_CNF" "$DUMP_TMP" "${DUMP_ERR:-}" "${SSH_ERR:-}" "${REMOTE_CHECK_ERR:-}"
}

on_interrupt() {
  echo "" >&2
  if [[ -n "$CURRENT_PHASE" ]]; then
    fail 130 "Interrompido pelo utilizador durante: ${CURRENT_PHASE}"
  else
    fail 130 "Interrompido pelo utilizador."
  fi
}

trap cleanup EXIT
trap on_interrupt INT TERM

while [[ $# -gt 0 ]]; do
  case "$1" in
    --yes|-y)
      SKIP_CONFIRM=true
      shift
      ;;
    --keep-dump)
      KEEP_DUMP=true
      shift
      ;;
    -h|--help)
      sed -n '1,18p' "$0"
      exit 0
      ;;
    *)
      fail 1 "Argumento desconhecido: $1"
      ;;
  esac
done

step "Pré-validação local"
[[ -f .env ]] || fail 1 "Arquivo .env não encontrado na raiz do projeto."
[[ -f "$ENV_FILE" ]] || fail 1 "Arquivo não encontrado: ${ENV_FILE} (copie/configure .env_deploy)."
[[ -f vendor/autoload.php ]] || fail 1 "vendor/autoload.php ausente. Rode: composer install"

for bin in mysqldump mysql gzip ssh; do
  command -v "$bin" >/dev/null 2>&1 || fail 1 "Comando obrigatório não encontrado no PATH: ${bin}"
done

load_var() {
  local key="$1"
  local line
  line="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%\"}"
  line="${line#\"}"
  printf '%s' "$line"
}

# Lê DB_* exclusivamente do .env local (ignora variáveis exportadas no shell).
read_local_db_config() {
  env -u DB_CONNECTION -u DB_HOST -u DB_PORT -u DB_DATABASE -u DB_USERNAME -u DB_PASSWORD \
    php -d xdebug.mode=off -r "
\$envFile = '${ROOT}/.env';
if (!is_readable(\$envFile)) {
  fwrite(STDERR, '.env ilegível'.PHP_EOL);
  exit(1);
}
\$vars = [];
foreach (file(\$envFile, FILE_IGNORE_NEW_LINES) ?: [] as \$line) {
  \$line = trim(\$line);
  if (\$line === '' || str_starts_with(\$line, '#')) {
    continue;
  }
  if (!str_contains(\$line, '=')) {
    continue;
  }
  [\$k, \$v] = explode('=', \$line, 2);
  \$k = trim(\$k);
  \$v = trim(\$v);
  if (\$v !== '' && ((\$v[0] === '\"' && str_ends_with(\$v, '\"')) || (\$v[0] === \"'\" && str_ends_with(\$v, \"'\")))) {
    \$v = substr(\$v, 1, -1);
  }
  \$vars[\$k] = \$v;
}
echo (\$vars['DB_HOST'] ?? '127.0.0.1').PHP_EOL;
echo (\$vars['DB_PORT'] ?? '3306').PHP_EOL;
echo (\$vars['DB_DATABASE'] ?? 'financial').PHP_EOL;
echo (\$vars['DB_USERNAME'] ?? 'root').PHP_EOL;
echo (\$vars['DB_PASSWORD'] ?? '').PHP_EOL;
echo (\$vars['APP_ENV'] ?? 'local').PHP_EOL;
"
}

SSH_HOST="$(load_var SSH_HOST)"
SSH_USER="$(load_var SSH_USER)"
SSH_PORT="$(load_var SSH_PORT)"
SSH_PASSWORD="$(load_var SSH_PASSWORD)"
REMOTE_DIR="$(load_var DEPLOY_REMOTE_DIR)"
DEPLOY_WEB_USER="$(load_var DEPLOY_WEB_USER)"

: "${SSH_HOST:?SSH_HOST ausente em $ENV_FILE}"
: "${REMOTE_DIR:?DEPLOY_REMOTE_DIR ausente em $ENV_FILE}"

SSH_PORT="${SSH_PORT:-22}"
SSH_USER="${SSH_USER:-root}"
DEPLOY_WEB_USER="${DEPLOY_WEB_USER:-admin}"

if [[ -n "${SSH_PASSWORD:-}" ]] && ! command -v sshpass >/dev/null 2>&1; then
  fail 1 "SSH_PASSWORD definido em ${ENV_FILE}, mas sshpass não está instalado."
fi

RUN() {
  if [[ -n "${SSH_PASSWORD:-}" ]] && command -v sshpass &>/dev/null; then
    SSHPASS="$SSH_PASSWORD" sshpass -e ssh \
      -o StrictHostKeyChecking=accept-new \
      -o ConnectTimeout=15 \
      -o ServerAliveInterval=15 \
      -o ServerAliveCountMax=4 \
      -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "$@"
  else
    ssh \
      -o StrictHostKeyChecking=accept-new \
      -o ConnectTimeout=15 \
      -o ServerAliveInterval=15 \
      -o ServerAliveCountMax=4 \
      -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" "$@"
  fi
}

step "Ler credenciais locais (.env)"
PHP_ERR=$(mktemp)
if ! {
  read -r LOCAL_HOST
  read -r LOCAL_PORT
  read -r LOCAL_DATABASE
  read -r LOCAL_USERNAME
  read -r LOCAL_PASSWORD
  read -r LOCAL_APP_ENV
} < <(read_local_db_config 2>"$PHP_ERR"); then
  show_file_errors "php" "$PHP_ERR"
  fail 1 "Falha ao ler DB_* do .env."
fi
rm -f "$PHP_ERR"

if [[ -z "$LOCAL_DATABASE" ]]; then
  fail 1 "DB_DATABASE vazio no .env."
fi

if [[ -z "$LOCAL_USERNAME" ]]; then
  fail 1 "DB_USERNAME vazio no .env."
fi

if [[ "$LOCAL_APP_ENV" == "production" ]]; then
  fail 1 "APP_ENV=production no .env local — abortado por segurança. Use ambiente de desenvolvimento."
fi

if [[ "$LOCAL_DATABASE" == "financial_test" ]]; then
  fail 1 "DB_DATABASE=financial_test — use o banco de dev (ex.: financial), não o de testes."
fi

# Operações locais usam DB_USERNAME/DB_PASSWORD do .env.
# MYSQL_ADMIN_* só quando precisar de privilégios extra (ex.: root para CREATE DATABASE).
ADMIN_USER="${MYSQL_ADMIN_USER:-$LOCAL_USERNAME}"
ADMIN_PASSWORD="${MYSQL_ADMIN_PASSWORD:-$LOCAL_PASSWORD}"
USE_ADMIN_OVERRIDE=false
if [[ -n "${MYSQL_ADMIN_USER:-}" || -n "${MYSQL_ADMIN_PASSWORD:-}" ]]; then
  USE_ADMIN_OVERRIDE=true
fi

write_my_cnf() {
  local file="$1"
  local host="$2"
  local port="$3"
  local user="$4"
  local password="$5"
  chmod 600 "$file"
  {
    echo '[client]'
    echo "host=${host}"
    echo "port=${port}"
    echo "user=${user}"
    echo "password=${password}"
  } > "$file"
}

MY_CNF=$(mktemp)
ADMIN_CNF=$(mktemp)
DUMP_TMP=$(mktemp --suffix=.sql.gz)
DUMP_ERR=$(mktemp)
SSH_ERR=$(mktemp)
REMOTE_CHECK_ERR=$(mktemp)

write_my_cnf "$MY_CNF" "$LOCAL_HOST" "$LOCAL_PORT" "$LOCAL_USERNAME" "$LOCAL_PASSWORD"
write_my_cnf "$ADMIN_CNF" "$LOCAL_HOST" "$LOCAL_PORT" "$ADMIN_USER" "$ADMIN_PASSWORD"

echo "    Produção: ${SSH_USER}@${SSH_HOST}:${SSH_PORT}"
echo "    Remoto:   ${REMOTE_DIR}"
echo "    Local:    ${LOCAL_DATABASE} @ ${LOCAL_HOST}:${LOCAL_PORT} (utilizador: ${LOCAL_USERNAME}, senha: .env)"
if [[ "$USE_ADMIN_OVERRIDE" == true ]]; then
  echo "    Admin:    ${ADMIN_USER} (MYSQL_ADMIN_* definido)"
fi
echo ""
echo "ATENÇÃO: todos os dados atuais do banco local '${LOCAL_DATABASE}' serão substituídos."

if [[ "$SKIP_CONFIRM" == false ]]; then
  read -r -p "Continuar? [y/N] " answer
  if [[ ! "$answer" =~ ^[Yy]$ ]]; then
    echo "Cancelado."
    exit 0
  fi
fi

step "Fase 0 — testar SSH"
if ! RUN "echo ok" > /dev/null 2>"$SSH_ERR"; then
  show_file_errors "ssh" "$SSH_ERR"
  fail 1 "Não foi possível conectar via SSH em ${SSH_USER}@${SSH_HOST}:${SSH_PORT}."
fi
echo "    SSH OK."

step "Fase 0 — validar ambiente remoto"
REMOTE_INFO=""
if ! REMOTE_INFO="$(RUN "REMOTE_DIR='$REMOTE_DIR' bash -s" 2>"$REMOTE_CHECK_ERR" <<'REMOTE_CHECK'
set -euo pipefail

fail_remote() {
  echo "REMOTE_ERROR: $*" >&2
  exit 1
}

[[ -d "$REMOTE_DIR" ]] || fail_remote "Diretório remoto inexistente: $REMOTE_DIR"
[[ -f "$REMOTE_DIR/.env" ]] || fail_remote "Arquivo .env remoto ausente: $REMOTE_DIR/.env"

read_env() {
  local key="$1"
  grep -E "^${key}=" "$REMOTE_DIR/.env" 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d '"' || true
}

DB_NAME="$(read_env DB_DATABASE)"
DB_USER="$(read_env DB_USERNAME)"
DB_PASS="$(read_env DB_PASSWORD)"
DB_HOST="$(read_env DB_HOST)"
DB_PORT="$(read_env DB_PORT)"

if [[ -f "$REMOTE_DIR/.deploy-secrets" ]]; then
  set +u
  # shellcheck disable=SC1091
  source "$REMOTE_DIR/.deploy-secrets"
  set -u
  DB_PASS="${DB_PASSWORD:-$DB_PASS}"
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

[[ -n "$DB_NAME" ]] || fail_remote "DB_DATABASE ausente no .env remoto."
[[ -n "$DB_USER" ]] || fail_remote "DB_USERNAME ausente no .env remoto."

command -v mysqldump >/dev/null 2>&1 || fail_remote "mysqldump não encontrado no servidor."
command -v mysql >/dev/null 2>&1 || fail_remote "mysql client não encontrado no servidor."
command -v gzip >/dev/null 2>&1 || fail_remote "gzip não encontrado no servidor."

MY_CNF="$(mktemp)"
chmod 600 "$MY_CNF"
trap 'rm -f "$MY_CNF"' EXIT
{
  echo '[client]'
  echo "host=${DB_HOST}"
  echo "port=${DB_PORT}"
  echo "user=${DB_USER}"
  echo "password=${DB_PASS}"
} > "$MY_CNF"

if ! mysql --defaults-file="$MY_CNF" -N -e "SELECT 1" >/dev/null 2>&1; then
  fail_remote "MySQL remoto inacessível (host=${DB_HOST}, port=${DB_PORT}, user=${DB_USER}, db=${DB_NAME}). Verifique credenciais em .env / .deploy-secrets."
fi

if ! mysql --defaults-file="$MY_CNF" -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}'" 2>/dev/null | grep -qx "1"; then
  fail_remote "Banco remoto '${DB_NAME}' não existe no MySQL de produção."
fi

TABLE_COUNT="$(mysql --defaults-file="$MY_CNF" -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}'" 2>/dev/null || echo "?")"
echo "DB_NAME=${DB_NAME}"
echo "DB_HOST=${DB_HOST}"
echo "DB_PORT=${DB_PORT}"
echo "DB_USER=${DB_USER}"
echo "TABLE_COUNT=${TABLE_COUNT}"
REMOTE_CHECK
)"; then
  show_file_errors "validação remota" "$REMOTE_CHECK_ERR"
  if grep -q '^REMOTE_ERROR:' "$REMOTE_CHECK_ERR" 2>/dev/null; then
    sed 's/^REMOTE_ERROR: /      /' "$REMOTE_CHECK_ERR" >&2
  fi
  fail 1 "Validação do ambiente remoto falhou."
fi

REMOTE_DB_NAME="$(printf '%s\n' "$REMOTE_INFO" | sed -n 's/^DB_NAME=//p' | tail -n 1)"
REMOTE_TABLE_COUNT="$(printf '%s\n' "$REMOTE_INFO" | sed -n 's/^TABLE_COUNT=//p' | tail -n 1)"
echo "    Banco remoto: ${REMOTE_DB_NAME} (${REMOTE_TABLE_COUNT} tabelas)"

step "Fase 1 — dump do MySQL em produção"
echo "    Aguarde — o dump pode demorar vários minutos conforme o tamanho dos dados."

watch_dump_progress() {
  local flag_file="$1"
  local dump_file="$2"
  local last_size=0
  while [[ -f "$flag_file" ]]; do
    if [[ -f "$dump_file" ]]; then
      local size
      size=$(stat -c%s "$dump_file" 2>/dev/null || echo 0)
      if (( size > last_size )); then
        printf '\r    Recebido: %s bytes...' "$size"
        last_size=$size
      fi
    fi
    sleep 2
  done
  printf '\n'
}

: > "$DUMP_ERR"
PROGRESS_FLAG=$(mktemp)
touch "$PROGRESS_FLAG"
watch_dump_progress "$PROGRESS_FLAG" "$DUMP_TMP" &
PROGRESS_PID=$!

DUMP_RC=0
if ! RUN "REMOTE_DIR='$REMOTE_DIR' bash -s" > "$DUMP_TMP" 2>>"$DUMP_ERR" <<'REMOTE_DUMP'
set -euo pipefail

fail_remote() {
  echo "REMOTE_ERROR: $*" >&2
  exit 1
}

cd "$REMOTE_DIR" || fail_remote "Não foi possível entrar em $REMOTE_DIR"

read_env() {
  local key="$1"
  grep -E "^${key}=" .env 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d '"' || true
}

DB_NAME="$(read_env DB_DATABASE)"
DB_USER="$(read_env DB_USERNAME)"
DB_PASS="$(read_env DB_PASSWORD)"
DB_HOST="$(read_env DB_HOST)"
DB_PORT="$(read_env DB_PORT)"

if [[ -f .deploy-secrets ]]; then
  set +u
  # shellcheck disable=SC1091
  source .deploy-secrets
  set -u
  DB_PASS="${DB_PASSWORD:-$DB_PASS}"
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

[[ -n "$DB_NAME" ]] || fail_remote "DB_DATABASE ausente no .env remoto."
[[ -n "$DB_USER" ]] || fail_remote "DB_USERNAME ausente no .env remoto."

MY_CNF="$(mktemp)"
chmod 600 "$MY_CNF"
trap 'rm -f "$MY_CNF"' EXIT
{
  echo '[client]'
  echo "host=${DB_HOST}"
  echo "port=${DB_PORT}"
  echo "user=${DB_USER}"
  echo "password=${DB_PASS}"
} > "$MY_CNF"

if ! mysqldump --defaults-file="$MY_CNF" \
  --single-transaction --routines --triggers \
  "$DB_NAME" 2>"$MY_CNF.err" | gzip -9 -c; then
  if [[ -s "$MY_CNF.err" ]]; then
    sed 's/^/REMOTE_ERROR: /' "$MY_CNF.err" >&2
  fi
  fail_remote "mysqldump falhou para o banco ${DB_NAME}."
fi
REMOTE_DUMP
then
  DUMP_RC=1
fi

rm -f "$PROGRESS_FLAG"
kill "$PROGRESS_PID" 2>/dev/null || true
wait "$PROGRESS_PID" 2>/dev/null || true
PROGRESS_PID=""

if [[ "$DUMP_RC" -ne 0 ]]; then
  show_file_errors "dump remoto" "$DUMP_ERR"
  if grep -qi 'REMOTE_ERROR:' "$DUMP_ERR" 2>/dev/null; then
    grep 'REMOTE_ERROR:' "$DUMP_ERR" | sed 's/REMOTE_ERROR: /      /' >&2
  fi
  rm -f "$DUMP_TMP"
  fail 1 "Falha ao gerar dump em produção."
fi

if [[ ! -s "$DUMP_TMP" ]]; then
  show_file_errors "dump remoto" "$DUMP_ERR"
  fail 1 "Dump vazio recebido do servidor (0 bytes). Verifique permissões de mysqldump e se o banco tem dados."
fi

if ! gzip -t "$DUMP_TMP" 2>"$DUMP_ERR"; then
  show_file_errors "gzip inválido" "$DUMP_ERR"
  fail 1 "Arquivo recebido não é um gzip válido (possível erro SSH ou saída corrompida)."
fi

DUMP_SIZE=$(du -h "$DUMP_TMP" | cut -f1)
DUMP_BYTES=$(stat -c%s "$DUMP_TMP" 2>/dev/null || echo "?")
echo "    Dump recebido: ${DUMP_SIZE} (${DUMP_BYTES} bytes)"

if [[ "$KEEP_DUMP" == true ]]; then
  BACKUP_DIR="${ROOT}/storage/backups"
  if ! mkdir -p "$BACKUP_DIR" 2>"$DUMP_ERR"; then
    show_file_errors "mkdir backups" "$DUMP_ERR"
    fail 1 "Não foi possível criar ${BACKUP_DIR}."
  fi
  STAMP=$(date +%Y-%m-%d_%H%M%S)
  SAVED="${BACKUP_DIR}/prod_${LOCAL_DATABASE}_${STAMP}.sql.gz"
  if ! cp "$DUMP_TMP" "$SAVED" 2>"$DUMP_ERR"; then
    show_file_errors "copiar dump" "$DUMP_ERR"
    fail 1 "Não foi possível guardar cópia em ${SAVED}."
  fi
  echo "    Cópia guardada: ${SAVED}"
fi

step "Fase 2 — testar MySQL local"
LOCAL_PING_ERR=$(mktemp)
if ! run_mysql "$MY_CNF" -N -e "SELECT 1" >/dev/null 2>"$LOCAL_PING_ERR"; then
  show_file_errors "mysql local" "$LOCAL_PING_ERR"
  fail 1 "MySQL local inacessível em ${LOCAL_HOST}:${LOCAL_PORT} (utilizador: ${LOCAL_USERNAME}). Verifique se o serviço está ligado."
fi
rm -f "$LOCAL_PING_ERR"
echo "    MySQL local OK."

step "Fase 2 — preparar banco local"
db_exists() {
  run_mysql "$ADMIN_CNF" -N -e \
    "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '${LOCAL_DATABASE}'" \
    2>/dev/null | grep -qx "$LOCAL_DATABASE"
}

DB_PREP_ERR=$(mktemp)
if db_exists; then
  echo "    Banco '${LOCAL_DATABASE}' existe — a apagar e recriar..."
  if ! run_mysql "$ADMIN_CNF" -e \
    "DROP DATABASE \`${LOCAL_DATABASE}\`; CREATE DATABASE \`${LOCAL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
    > /dev/null 2>"$DB_PREP_ERR"; then
    show_file_errors "recriar banco" "$DB_PREP_ERR"
    fail 1 "Sem permissão para DROP/CREATE DATABASE '${LOCAL_DATABASE}'. Defina MYSQL_ADMIN_USER=root (ou rode scripts/setup-mysql-local.sh)."
  fi
else
  echo "    Banco '${LOCAL_DATABASE}' não existe — a criar..."
  if ! run_mysql "$ADMIN_CNF" -e \
    "CREATE DATABASE \`${LOCAL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
    > /dev/null 2>"$DB_PREP_ERR"; then
    show_file_errors "criar banco" "$DB_PREP_ERR"
    fail 1 "Sem permissão para CREATE DATABASE '${LOCAL_DATABASE}'. Rode scripts/setup-mysql-local.sh ou defina MYSQL_ADMIN_USER."
  fi
fi
rm -f "$DB_PREP_ERR"

GRANT_ERR=$(mktemp)
if ! run_mysql "$ADMIN_CNF" -e \
  "GRANT ALL PRIVILEGES ON \`${LOCAL_DATABASE}\`.* TO '${LOCAL_USERNAME}'@'localhost'; GRANT ALL PRIVILEGES ON \`${LOCAL_DATABASE}\`.* TO '${LOCAL_USERNAME}'@'127.0.0.1'; FLUSH PRIVILEGES;" \
  > /dev/null 2>"$GRANT_ERR"; then
  warn "Não foi possível garantir GRANT para '${LOCAL_USERNAME}' (pode já ter permissão suficiente)."
  show_file_errors "grant" "$GRANT_ERR"
fi
rm -f "$GRANT_ERR"

step "Fase 3 — restaurar dump no banco local"
echo "    A importar ${DUMP_SIZE} em '${LOCAL_DATABASE}'..."
RESTORE_ERR=$(mktemp)
if ! gunzip -c "$DUMP_TMP" 2>"$RESTORE_ERR" | mysql --defaults-file="$MY_CNF" "$LOCAL_DATABASE" >>"$RESTORE_ERR" 2>&1; then
  show_file_errors "restore mysql" "$RESTORE_ERR"
  fail 1 "Falha ao restaurar dump em '${LOCAL_DATABASE}'. Teste: php artisan db:show"
fi
rm -f "$RESTORE_ERR"
echo "    Restore concluído."

step "Fase 4 — migrations pendentes (estrutura)"
MIGRATE_ERR=$(mktemp)
if ! env -u DB_USERNAME -u DB_PASSWORD php -d xdebug.mode=off artisan migrate --force > /dev/null 2>"$MIGRATE_ERR"; then
  warn "migrate falhou — dados restaurados, mas a estrutura pode estar desatualizada."
  show_file_errors "artisan migrate" "$MIGRATE_ERR"
else
  echo "    Migrations OK."
fi
rm -f "$MIGRATE_ERR"

echo ""
echo "Concluído. Banco local '${LOCAL_DATABASE}' atualizado com dados de produção (${REMOTE_DB_NAME})."
echo ""
echo "Lembrete:"
echo "  - Senhas de utilizadores são as de produção; altere se necessário."
echo "  - Comprovantes ficam em storage/app/private — copie à parte se precisar."
echo "  - Teste: php artisan db:show && php artisan serve"
