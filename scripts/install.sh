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
# Porta onde o Apache DESTA instalacao escuta. So mude se o servidor ja
# tiver outro web server (ex: nginx) usando a 80/443 -- nesse caso, use
# uma porta interna (ex: 8080) e configure um reverse proxy no nginx do
# cliente apontando pra ela (ver docs/INSTALACAO.md).
APACHE_PORT="${APACHE_PORT:-80}"

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
  # instalacao retomada apos uma falha anterior -- atualiza pro commit
  # atual em vez de so pular, senao o resto do script (que roda a partir
  # deste install.sh, ja atualizado) vai referenciar arquivos que o
  # checkout antigo em REPO_DIR ainda nao tem.
  echo "Ja existe um checkout em $REPO_DIR, atualizando em vez de clonar."
  sudo -u "$REPO_USER" git -C "$REPO_DIR" -c safe.directory="$REPO_DIR" fetch origin "$REPO_BRANCH" --quiet
  sudo -u "$REPO_USER" git -C "$REPO_DIR" -c safe.directory="$REPO_DIR" merge --ff-only "origin/$REPO_BRANCH"
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
# mariadb-server e libapache2-mod-php nao vem do catalogo de dependencias
# porque aquela lista e sobre o que a aplicacao *usa em runtime* (cliente
# mysql, ferramentas de rede etc) -- servidor de banco local e o modulo do
# PHP no Apache sao pre-requisito da propria aplicacao rodar, entram numa
# instalacao nova sempre.
apt-get install -y -qq $PACOTES composer mariadb-server libapache2-mod-php

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
# 4.1) Passos de setup que criam conta/chave/servico privilegiado -- de
#      proposito FORA do sudoers automatico do www-data (nao terminam em
#      _web.sh), entao o install.sh precisa rodar cada um explicitamente.
#      Todos idempotentes.
# ---------------------------------------------------------------------
for SETUP in setup_acl_admin setup_db_secret_key setup_iptables_persistencia setup_rotas_extras; do
  bash "$REPO_DIR/scripts/system/${SETUP}.sh"
done

# ---------------------------------------------------------------------
# 5) Banco de dados
# ---------------------------------------------------------------------
if [ -f "$REPO_DIR/app/Config/database.php" ]; then
  # instalacao retomada apos uma falha anterior -- reaproveita a senha que
  # ja esta no arquivo em vez de gerar outra, senao o ALTER USER abaixo
  # dessincroniza arquivo e banco a cada nova tentativa.
  DB_SENHA="$(php -r "echo (require '$REPO_DIR/app/Config/database.php')['password'];")"
else
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
ALTER USER '${DB_USUARIO}'@'localhost' IDENTIFIED BY '${DB_SENHA}';
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

# smb.conf [global] + include de shares.conf -- depende do autoload do
# composer (usa App\Core\Samba\SambaTemplate::global(), mesmo template da
# tela Samba > Config. Global). Sem isso, "Deploy > Aplicar Samba" falha e
# nenhum compartilhamento fica acessivel de verdade pela rede
# (NT_STATUS_BAD_NETWORK_NAME), mesmo ja existindo no banco.
bash "$REPO_DIR/scripts/system/setup_samba_base.sh" "$REPO_DIR"

php -r '
require "vendor/autoload.php";
$pdo = App\Core\Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations_aplicadas (arquivo VARCHAR(180) NOT NULL PRIMARY KEY, aplicado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$stmt = $pdo->prepare("INSERT IGNORE INTO migrations_aplicadas (arquivo) VALUES (?)");
foreach (glob("database/migrations/*.sql") as $f) { $stmt->execute([basename($f)]); }
'

php "$REPO_DIR/rd" migrate

# ---------------------------------------------------------------------
# 6.1) Usuario admin padrao, so se ainda nao existir nenhum admin (idempotente
#      em instalacao retomada). Senha fixa e conhecida de proposito -- e
#      so pra dar o primeiro acesso; troque assim que logar.
# ---------------------------------------------------------------------
php -r '
require "vendor/autoload.php";
$pdo = App\Core\Database::connection();
$existeAdmin = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = \"admin\"")->fetchColumn();
if ($existeAdmin === 0) {
    $hash = password_hash("rd.intranet", PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, senha_hash, perfil, ativo) VALUES (?, ?, ?, \"admin\", 1)");
    $stmt->execute(["Administrador", "admin", $hash]);
    echo "Usuario admin padrao criado.\n";
} else {
    echo "Ja existe usuario admin, nada a criar.\n";
}
'

# ---------------------------------------------------------------------
# 6.2) Crons nativos de coleta (trafego de rede, contadores e logs do
#      firewall) -- nao sao "recursos opcionais" que o admin liga depois
#      (diferente da verificacao diaria de atualizacoes), sao parte dos
#      proprios modulos de historico/grafico. Sem eles, as telas
#      correspondentes ficam pra sempre vazias num servidor novo.
#      Mesma logica que AtualizacaoService::garantirCronsColeta() roda a
#      cada "Atualizar agora" -- reaproveitada aqui, nao duplicada.
# ---------------------------------------------------------------------
php -r 'require $argv[1] . "/vendor/autoload.php"; (new App\Services\AtualizacaoService())->garantirCronsColeta();' "$REPO_DIR"

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

# storage/uploads (.gitignore -- nao vem do git clone), storage/cache e
# storage/logs sao excecao de proposito: www-data GRAVA neles em tempo
# de execucao (upload de arquivo, ex: .exe do agente em Ativos >
# Dashboard; cache; log da aplicacao), diferente do resto do checkout,
# que so REPO_USER grava. Sem isso, qualquer feature de upload falha com
# "Falha ao criar a pasta de destino no servidor" -- www-data nao
# consegue nem criar a pasta, quanto mais escrever nela.
mkdir -p "$REPO_DIR/storage/uploads" "$REPO_DIR/storage/cache" "$REPO_DIR/storage/logs"
chown -R www-data:www-data "$REPO_DIR/storage/uploads" "$REPO_DIR/storage/cache" "$REPO_DIR/storage/logs"

# ---------------------------------------------------------------------
# 9) Vhost Apache (HTTP; rode o modulo Certificado pela propria tela depois
#    do primeiro login pra ativar HTTPS)
# ---------------------------------------------------------------------
a2enmod rewrite >/dev/null
cat > "/etc/apache2/sites-available/rd-intranet.conf" <<EOF
<VirtualHost *:${APACHE_PORT}>
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

if [ "$APACHE_PORT" != "80" ]; then
  # porta nao-padrao (Apache convivendo com outro web server, ex: nginx,
  # que ja segura a 80/443) -- garante que o Apache escute nela e tira o
  # site padrao do caminho (ele so responde na 80).
  if ! grep -q "^Listen ${APACHE_PORT}\$" /etc/apache2/ports.conf; then
    echo "Listen ${APACHE_PORT}" >> /etc/apache2/ports.conf
  fi
  a2dissite 000-default >/dev/null 2>&1 || true
fi

# A aplicacao roda com base_url=/rd.intranet por padrao (ver 'configuracoes'
# no banco e app/Helpers/url.php) -- as rotas assumem esse prefixo na URL.
# O .htaccess usa RewriteBase /rd.intranet/ pra isso, mas RewriteBase so
# funciona quando o prefixo da URL de fato corresponde a um diretorio
# fisico diferente via Alias (documentado no proprio mod_rewrite); sem
# esse Alias, /rd.intranet/algo entra em loop de redirecionamento interno
# (AH00124) porque o prefixo nao existe como pasta real dentro de public/.
cat > "/etc/apache2/conf-available/rd-intranet-alias.conf" <<EOF
Alias /rd.intranet ${REPO_DIR}/public

<Directory ${REPO_DIR}/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
EOF
a2enconf rd-intranet-alias >/dev/null

# restart (nao so reload) pra garantir que qualquer modulo instalado nesta
# mesma execucao (rewrite, php) entre em vigor de fato -- ja vimos reload
# nao ser suficiente logo apos um modulo novo ser habilitado.
systemctl restart apache2

echo ""
echo "== Instalacao concluida =="
if [ "$APACHE_PORT" = "80" ]; then
  echo "Site (HTTP): http://${DOMINIO}/rd.intranet/login"
else
  echo "Apache respondendo internamente em http://127.0.0.1:${APACHE_PORT}/rd.intranet/login"
  echo "Falta configurar o reverse proxy no nginx do servidor -- ver secao"
  echo "'Rodando atras de nginx' em docs/INSTALACAO.md."
fi
echo ""
echo "Login admin padrao: admin / rd.intranet"
echo "!! TROQUE ESSA SENHA agora, no primeiro login (Administracao > Usuarios do Sistema) !!"
echo ""
echo "Banco '${DB_NOME}' criado, usuario '${DB_USUARIO}', senha: ${DB_SENHA}"
echo "(guarde essa senha agora -- ela nao fica no repositorio nem e reexibida)"
echo ""
echo "Falta:"
echo "  1. Logar e trocar a senha do admin padrao."
echo "  2. Emitir HTTPS pela tela Infraestrutura > Certificado Digital."
