#!/bin/bash
# install.sh
#
# Instala a RD Intranet do zero num servidor Ubuntu 24.04 novo. Roda uma
# unica vez, manualmente, como root:
#
#   sudo bash install.sh
#
# Pre-requisitos: servidor com acesso root, git instalado (ou sera
# instalado por este script) e uma deploy key com acesso de LEITURA ao
# repositorio ja cadastrada no GitHub e configurada em ~/.ssh (ou de quem
# for o dono do checkout, ver REPO_USER abaixo) -- este script clona via
# SSH, nao pede usuario/senha do GitHub interativamente.
#
# Primeira versao: cobre o caminho feliz (Ubuntu 24.04 limpo). Revise antes
# de rodar num servidor real; o ideal e validar antes numa VM de teste.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo bash install.sh)." >&2
  exit 1
fi

# ---------------------------------------------------------------------
# Parametros (todos com valor padrao; exporte antes de rodar pra mudar)
# ---------------------------------------------------------------------
REPO_URL="${REPO_URL:-git@github.com:raonidore/rd-intranet.git}"
REPO_BRANCH="${REPO_BRANCH:-main}"
REPO_DIR="${REPO_DIR:-/var/www/rd.intranet}"
REPO_USER="${REPO_USER:-ti}"
DOMINIO="${DOMINIO:-}"
DB_NOME="${DB_NOME:-rd_intranet}"
DB_USUARIO="${DB_USUARIO:-rd_intranet}"
DB_SENHA="${DB_SENHA:-$(set +o pipefail; tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24)}"

echo "== RD Intranet: instalacao =="
echo "Repositorio: $REPO_URL ($REPO_BRANCH) -> $REPO_DIR (dono: $REPO_USER)"

if [ -z "$DOMINIO" ]; then
  read -rp "Dominio/host deste servidor (ex: intranet.suaempresa.com.br): " DOMINIO
fi

if ! id "$REPO_USER" >/dev/null 2>&1; then
  echo "Usuario '$REPO_USER' nao existe no sistema. Crie-o antes (adduser $REPO_USER) e rode de novo." >&2
  exit 1
fi

# ---------------------------------------------------------------------
# 1) Pacotes minimos pra clonar e rodar o PHP (o resto vem do catalogo de
#    dependencias abaixo, ja que ele passa a existir apos o clone)
# ---------------------------------------------------------------------
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git php-cli php-mysql php-xml php-mbstring unzip curl

# ---------------------------------------------------------------------
# 2) Clona o repositorio como REPO_USER (mantem o dono correto desde o
#    inicio -- o resto da aplicacao roda como www-data, que so LE esses
#    arquivos, nunca escreve, ver docs/INSTALACAO.md)
# ---------------------------------------------------------------------
if [ -d "$REPO_DIR/.git" ]; then
  echo "Ja existe um checkout em $REPO_DIR, pulando clone."
else
  # /var/www e do root; REPO_DIR precisa existir e ja pertencer ao
  # REPO_USER *antes* do clone, senao o "sudo -u" abaixo nao consegue
  # nem criar o diretorio.
  mkdir -p "$REPO_DIR"
  chown "$REPO_USER:$REPO_USER" "$REPO_DIR"
  sudo -u "$REPO_USER" git clone --branch "$REPO_BRANCH" "$REPO_URL" "$REPO_DIR"
fi

cd "$REPO_DIR"

# ---------------------------------------------------------------------
# 3) Resto dos pacotes do sistema, direto do catalogo de dependencias da
#    propria aplicacao (app/Services/DependenciaCatalogo.php) -- uma unica
#    lista, tambem usada pela tela Infraestrutura > Dependencias.
# ---------------------------------------------------------------------
PACOTES=$(php -r '
require "app/Services/DependenciaCatalogo.php";
foreach (App\Services\DependenciaCatalogo::itens() as $i) { echo $i["pacote"] . " "; }
')
echo "Instalando pacotes: $PACOTES"
# shellcheck disable=SC2086
# mariadb-server nao vem do catalogo de dependencias porque aquela lista e
# so o cliente (usado pelo Console SQL pra conectar em bancos externos) --
# o servidor local, que guarda os dados da propria aplicacao, so entra
# numa instalacao nova mesmo.
apt-get install -y -qq $PACOTES composer mariadb-server

systemctl enable --now mariadb >/dev/null

# ---------------------------------------------------------------------
# 4) Estrutura fora do repo usada pelos scripts root (ver
#    scripts/sync-system-scripts.sh e o modulo de Cron)
# ---------------------------------------------------------------------
mkdir -p /opt/rdtecnologia/scripts /opt/rdtecnologia/logs
mkdir -p /var/log/rd-intranet-cron
chmod 1777 /var/log/rd-intranet-cron

bash "$REPO_DIR/scripts/sync-system-scripts.sh"

# ---------------------------------------------------------------------
# 5) Banco de dados
# ---------------------------------------------------------------------
if [ ! -f "$REPO_DIR/app/Config/database.php" ]; then
  sudo -u "$REPO_USER" cp "$REPO_DIR/app/Config/database.example.php" "$REPO_DIR/app/Config/database.php"
  sudo -u "$REPO_USER" sed -i \
    -e "s/dbname=rd_intranet/dbname=${DB_NOME}/" \
    -e "s/'user' => 'rd_intranet'/'user' => '${DB_USUARIO}'/" \
    -e "s/'password' => 'troque-esta-senha'/'password' => '${DB_SENHA}'/" \
    "$REPO_DIR/app/Config/database.php"
fi

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NOME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USUARIO}'@'localhost' IDENTIFIED BY '${DB_SENHA}';
GRANT ALL PRIVILEGES ON \`${DB_NOME}\`.* TO '${DB_USUARIO}'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql "$DB_NOME" < "$REPO_DIR/database/schema.sql"

# ---------------------------------------------------------------------
# 6) Composer + marca as migrations incrementais como ja aplicadas (o
#    schema.sql acima ja reflete o estado final delas -- rodar de novo
#    quebraria nas que fazem ALTER TABLE) + roda qualquer migration REALMENTE
#    nova que exista no repo depois da data do schema.sql
# ---------------------------------------------------------------------
sudo -u "$REPO_USER" composer install --no-dev --optimize-autoloader --no-interaction --working-dir="$REPO_DIR"

php -r '
require "vendor/autoload.php";
$pdo = App\Core\Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations_aplicadas (arquivo VARCHAR(180) NOT NULL PRIMARY KEY, aplicado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$stmt = $pdo->prepare("INSERT IGNORE INTO migrations_aplicadas (arquivo) VALUES (?)");
foreach (glob("database/migrations/*.sql") as $f) { $stmt->execute([basename($f)]); }
'

php "$REPO_DIR/rd" migrate

# ---------------------------------------------------------------------
# 7) sudoers: www-data pode rodar, sem senha, qualquer script ja aprovado
#    dentro de /opt/rdtecnologia/scripts (diretorio root-only -- www-data
#    nao consegue escrever nem trocar nenhum desses arquivos). Cada script
#    revalida sozinho o que aceita, ver comentarios em scripts/system/*.sh.
# ---------------------------------------------------------------------
if [ ! -f /etc/sudoers.d/rd-intranet ]; then
  cat > /etc/sudoers.d/rd-intranet <<'EOF'
# Gerado por install.sh -- www-data (PHP da RD Intranet) pode rodar, sem
# senha, qualquer script ja publicado em /opt/rdtecnologia/scripts (dono
# root, www-data nao tem escrita ali). Cada script valida sozinho o que
# aceita como argumento.
www-data ALL=(root) NOPASSWD: /opt/rdtecnologia/scripts/*.sh
EOF
  chown root:root /etc/sudoers.d/rd-intranet
  chmod 440 /etc/sudoers.d/rd-intranet
  visudo -c -f /etc/sudoers.d/rd-intranet
fi

# ---------------------------------------------------------------------
# 8) Permissoes: www-data serve o site (le tudo), REPO_USER continua dono
#    e quem grava (deploy/update sempre roda "sudo -u $REPO_USER git ...")
# ---------------------------------------------------------------------
chown -R "$REPO_USER:$REPO_USER" "$REPO_DIR"
chmod -R u+rwX,g+rwX,o+rX-w "$REPO_DIR"

# ---------------------------------------------------------------------
# 9) Vhost Apache (HTTP; rode o modulo Certificado pela propria tela depois
#    do primeiro login pra ativar HTTPS)
# ---------------------------------------------------------------------
a2enmod rewrite >/dev/null
cat > "/etc/apache2/sites-available/rd-intranet.conf" <<EOF
<VirtualHost *:80>
    ServerName ${DOMINIO}
    DocumentRoot ${REPO_DIR}/public

    <Directory ${REPO_DIR}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/rd-intranet-error.log
    CustomLog \${APACHE_LOG_DIR}/rd-intranet-access.log combined
</VirtualHost>
EOF
a2ensite rd-intranet >/dev/null
systemctl reload apache2

echo ""
echo "== Instalacao concluida =="
echo "Site (HTTP): http://${DOMINIO}/"
echo "Banco '${DB_NOME}' criado, usuario '${DB_USUARIO}', senha: ${DB_SENHA}"
echo "(guarde essa senha agora -- ela nao fica no repositorio nem e reexibida)"
echo ""
echo "Falta:"
echo "  1. Criar o primeiro usuario admin (nao ha tela pra isso ainda sem"
echo "     login -- insira direto no banco, ver docs/INSTALACAO.md)."
echo "  2. Emitir HTTPS pela tela Infraestrutura > Certificado Digital."
