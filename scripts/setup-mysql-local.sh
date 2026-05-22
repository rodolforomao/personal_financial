#!/usr/bin/env bash
# Configura MySQL local (systemd, porta 3306) para o projeto.
# Requer acesso admin — use o mesmo utilizador do seu gestor de BD (ex.: root).

set -euo pipefail
cd "$(dirname "$0")/.."

ADMIN_USER="${MYSQL_ADMIN_USER:-root}"
SQL_FILE="scripts/mysql-setup.sql"

echo "==> A criar base de dados e utilizador 'financial' no MySQL local (3306)..."
echo "    (será pedida a password do utilizador: ${ADMIN_USER})"
echo ""

mysql -h 127.0.0.1 -P 3306 -u "${ADMIN_USER}" -p < "${SQL_FILE}"

echo ""
echo "==> A testar ligação..."
mysql -h 127.0.0.1 -P 3306 -u financial -psecret -e "USE financial; SELECT 'OK' AS status;"

# Garantir .env na porta 3306
if grep -q '^DB_PORT=3307' .env 2>/dev/null; then
  sed -i 's/^DB_PORT=3307/DB_PORT=3306/' .env
fi

echo ""
echo "==> A migrar..."
php artisan migrate --seed --force

echo ""
echo "Pronto. MySQL local (3306) configurado."
echo "  php artisan serve"
echo "  Login: admin@financial.local / password"
