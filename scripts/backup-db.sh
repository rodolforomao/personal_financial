#!/usr/bin/env bash
# Backup do banco MySQL configurado no .env (dados reais de dev).
# Saída: storage/backups/financial_YYYY-MM-DD_HHMMSS.sql.gz (não vai para o Git)
set -euo pipefail
set +H 2>/dev/null || true

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Arquivo .env não encontrado." >&2
  exit 1
fi

if [[ ! -f vendor/autoload.php ]]; then
  echo "Rode composer install antes do backup." >&2
  exit 1
fi

# Mesmas credenciais que o Laravel (evita erro com !, $, etc. no .env)
{
  read -r DB_HOST
  read -r DB_PORT
  read -r DB_DATABASE
  read -r DB_USERNAME
  read -r DB_PASSWORD
} < <(php -d xdebug.mode=off -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$c = config('database.connections.mysql');
echo (\$c['host'] ?? '127.0.0.1').PHP_EOL;
echo (\$c['port'] ?? '3306').PHP_EOL;
echo (\$c['database'] ?? 'financial').PHP_EOL;
echo (\$c['username'] ?? 'root').PHP_EOL;
echo (\$c['password'] ?? '').PHP_EOL;
")

BACKUP_DIR="${ROOT}/storage/backups"
mkdir -p "$BACKUP_DIR"
STAMP=$(date +%Y-%m-%d_%H%M%S)
OUT="${BACKUP_DIR}/${DB_DATABASE}_${STAMP}.sql.gz"

echo "Backup: ${DB_DATABASE} @ ${DB_HOST}:${DB_PORT} (utilizador: ${DB_USERNAME})"
echo "Destino: ${OUT}"

MY_CNF=$(mktemp)
chmod 600 "$MY_CNF"
trap 'rm -f "$MY_CNF"' EXIT

{
  echo '[client]'
  echo "host=${DB_HOST}"
  echo "port=${DB_PORT}"
  echo "user=${DB_USERNAME}"
  echo "password=${DB_PASSWORD}"
} > "$MY_CNF"

# --defaults-file (não --defaults-extra-file): ignora ~/.my.cnf do sistema, que costuma
# ter outra senha e causa "Access denied" mesmo com .env correto.
if ! mysqldump --defaults-file="$MY_CNF" \
  --single-transaction --routines --triggers --no-tablespaces \
  "$DB_DATABASE" | gzip -9 > "$OUT"; then
  echo "" >&2
  echo "Falha no mysqldump. Teste: php artisan db:show" >&2
  echo "Credenciais vêm só do .env do projeto (não do ~/.my.cnf)." >&2
  rm -f "$OUT"
  exit 1
fi

ls -lh "$OUT"
echo ""
echo "Lembrete: copie também storage/app/private (comprovantes) se for restaurar em outro servidor."
