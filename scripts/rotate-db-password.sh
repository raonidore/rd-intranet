#!/bin/bash
# rotate-db-password.sh
#
# Troca a senha do usuario MySQL 'rd_intranet' por uma nova, gerada
# aleatoriamente, e atualiza app/Config/database.php (que NAO e versionado
# -- ver .gitignore) pra usar a senha nova. A senha nunca aparece no
# terminal nem em nenhum arquivo rastreado pelo git.
#
# Rodar uma vez, manualmente, como root:
#
#   sudo /var/www/rd.intranet/scripts/rotate-db-password.sh
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

REPO_DIR="/var/www/rd.intranet"
DB_USUARIO="rd_intranet"
CONFIG="$REPO_DIR/app/Config/database.php"

if [ ! -f "$CONFIG" ]; then
  echo "Nao encontrei $CONFIG." >&2
  exit 1
fi

NOVA_SENHA="$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)"

mysql <<SQL
ALTER USER '${DB_USUARIO}'@'localhost' IDENTIFIED BY '${NOVA_SENHA}';
FLUSH PRIVILEGES;
SQL

sed -i "s/'password' => '.*'/'password' => '${NOVA_SENHA}'/" "$CONFIG"
chown ti:ti "$CONFIG"

echo "OK: senha do usuario '${DB_USUARIO}' trocada e ${CONFIG} atualizado."
echo "A senha antiga (a que estava exposta no git) parou de funcionar agora."
