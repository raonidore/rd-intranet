#!/bin/bash
# config_backup_gerar_web.sh <execucao_id> [<remote> <destino_remoto>]
#
# Gera o pacote de backup TOTAL de configuracao (Sistema > Configuracoes):
# dump completo do banco da aplicacao + arquivos do sistema operacional que
# nao tem fonte no banco (chave de criptografia, senha dos usuarios Samba,
# PKI de VPN, certificado HTTPS, rotas/rede etc -- ver manifest.json pra
# lista exata). Tudo cifrado com uma senha lida via STDIN (nunca argv/disco
# em texto puro), pra permitir reconstruir o servidor do zero em caso de
# desastre (Sistema > Configuracoes > Restaurar).
#
# Chamado em segundo plano
# (LinuxService::executarScriptEmSegundoPlanoComEntrada) -- mysqldump +
# tar de PKI pode levar mais que o timeout de uma requisicao HTTP. Escreve
# o proprio progresso em storage/config_backup_status/<id>.json (mesmo
# esquema de backup_executar_web.sh/lote_arquivos_samba_web.sh).
#
# $2/$3 (remote/destino_remoto) sao opcionais -- se vierem preenchidos, o
# pacote cifrado tambem e enviado por rclone pro mesmo destino de nuvem ja
# configurado em Backup em Nuvem, numa subpasta _config_backup/.

set -uo pipefail

STATUS_DIR="/var/www/rd.intranet/storage/config_backup_status"
DEST_DIR="/var/www/rd.intranet/storage/config_backups"
mkdir -p "$STATUS_DIR" "$DEST_DIR"

EXECUCAO_ID="$1"
REMOTE="${2:-}"
DESTINO_REMOTO="${3:-}"

STATUS_FILE="$STATUS_DIR/${EXECUCAO_ID}.json"
ITER_PBKDF2=200000

escrever_status() {
  local status="$1" percentual="$2" mensagem="${3:-}" arquivo="${4:-}" tamanho="${5:-0}" nuvem="${6:-0}"
  php -r '
    echo json_encode([
        "status" => $argv[1],
        "percentual" => (int)$argv[2],
        "mensagem" => $argv[3],
        "arquivo" => $argv[4] ?: null,
        "tamanho_bytes" => (int)$argv[5],
        "enviado_nuvem" => (bool)(int)$argv[6],
        "atualizado_em" => time(),
    ]);
  ' -- "$status" "$percentual" "$mensagem" "$arquivo" "$tamanho" "$nuvem" > "$STATUS_FILE"
  chmod 644 "$STATUS_FILE"
}

if [[ ! "$EXECUCAO_ID" =~ ^[0-9]+$ ]]; then
  echo "ID de execucao invalido" >&2
  exit 1
fi

SENHA="$(cat -)"
if [ -z "$SENHA" ]; then
  escrever_status "erro" 0 "Senha de criptografia vazia."
  exit 1
fi

WORKDIR="/root/.rd_config_backup/${EXECUCAO_ID}"
rm -rf "$WORKDIR"
mkdir -p "$WORKDIR"
chmod 700 "$WORKDIR"

limpar() {
  # sobrescreve antes de apagar -- $WORKDIR tem o dump/tar SEM cifrar e a
  # propria SENHA nao fica em arquivo nenhum aqui, mas o dump/tar sim, ate
  # este ponto
  rm -rf "$WORKDIR"
}
trap limpar EXIT

escrever_status "rodando" 5 "Lendo credenciais do banco de dados..."

DB_CONF="/var/www/rd.intranet/app/Config/database.php"
if [ ! -f "$DB_CONF" ]; then
  escrever_status "erro" 0 "Arquivo de configuracao do banco (app/Config/database.php) nao encontrado."
  exit 1
fi

IFS='|' read -r DB_HOST DB_NOME DB_USUARIO DB_SENHA < <(php -r '
$c = require $argv[1];
preg_match("/host=([^;]+)/", $c["dsn"], $mh);
preg_match("/dbname=([^;]+)/", $c["dsn"], $md);
echo ($mh[1] ?? "localhost") . "|" . ($md[1] ?? "") . "|" . $c["user"] . "|" . $c["password"];
' "$DB_CONF")

if [ -z "$DB_NOME" ] || [ -z "$DB_USUARIO" ]; then
  escrever_status "erro" 0 "Nao foi possivel ler as credenciais do banco de dados."
  exit 1
fi

escrever_status "rodando" 15 "Gerando dump do banco de dados..."

if ! MYSQL_PWD="$DB_SENHA" mysqldump --single-transaction --routines --triggers --events \
    -h"$DB_HOST" -u"$DB_USUARIO" "$DB_NOME" > "$WORKDIR/database.sql" 2>"$WORKDIR/mysqldump.err"; then
  ERRO="$(tail -c 500 "$WORKDIR/mysqldump.err" 2>/dev/null)"
  escrever_status "erro" 0 "Falha ao gerar o dump do banco de dados: ${ERRO:-erro desconhecido}"
  exit 1
fi

escrever_status "rodando" 30 "Montando manifesto..."

GIT_HASH="$(cd /var/www/rd.intranet && git rev-parse --short HEAD 2>/dev/null || echo '')"

php -r '
$pdo = null;
try {
    $pdo = new PDO($argv[1], $argv[2], $argv[3]);
    $migrations = $pdo->query("SELECT arquivo FROM migrations_aplicadas ORDER BY arquivo")->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    $migrations = [];
}
echo json_encode([
    "gerado_em" => date("c"),
    "hostname" => gethostname(),
    "git_hash" => $argv[4] ?: null,
    "formato_versao" => 1,
    "migrations_aplicadas" => $migrations,
], JSON_PRETTY_PRINT) . "\n";
' "mysql:host={$DB_HOST};dbname={$DB_NOME};charset=utf8mb4" "$DB_USUARIO" "$DB_SENHA" "$GIT_HASH" > "$WORKDIR/manifest.json"

escrever_status "rodando" 45 "Coletando arquivos de configuracao do sistema..."

# cada caminho testado com [ -e ] antes -- ausencia (ex: OpenVPN nao
# instalado neste servidor) e normal e nao falha o backup, so fica de fora.
CAMINHOS_SO=(
  "/etc/rd-intranet/db_secret.key"
  "/etc/rd-intranet/rotas-extras.conf"
  "/etc/rd-intranet/.certificado-tipo"
  "/etc/rd-intranet/.certificado-dominio"
  "/etc/samba/smb.conf"
  "/var/lib/samba/private/passdb.tdb"
  "/etc/openvpn/server/easy-rsa/pki"
  "/etc/netplan/90-rd-intranet.yaml"
  "/etc/ssl/rd-intranet/atual.crt"
  "/etc/ssl/rd-intranet/atual.key"
  "/etc/letsencrypt"
  "/opt/meshcentral/meshcentral-data"
)
for CONF in /etc/wireguard/*.conf; do
  CAMINHOS_SO+=("$CONF")
done

TAR_ARGS=(--create --file "$WORKDIR/pacote.tar" -C "$WORKDIR" manifest.json database.sql)
INCLUIDOS=()
for CAMINHO in "${CAMINHOS_SO[@]}"; do
  if [ -e "$CAMINHO" ]; then
    TAR_ARGS+=(-C / "${CAMINHO#/}")
    INCLUIDOS+=("$CAMINHO")
  fi
done

escrever_status "rodando" 65 "Compactando pacote..."

if ! tar "${TAR_ARGS[@]}" 2>"$WORKDIR/tar.err"; then
  ERRO="$(tail -c 500 "$WORKDIR/tar.err" 2>/dev/null)"
  escrever_status "erro" 0 "Falha ao compactar o pacote: ${ERRO:-erro desconhecido}"
  exit 1
fi

escrever_status "rodando" 80 "Cifrando pacote..."

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
ARQUIVO_FINAL="config-backup-${TIMESTAMP}.tar.enc"
DESTINO_LOCAL="$DEST_DIR/$ARQUIVO_FINAL"

if ! printf '%s' "$SENHA" | openssl enc -aes-256-cbc -pbkdf2 -iter "$ITER_PBKDF2" -salt -pass stdin \
    -in "$WORKDIR/pacote.tar" -out "$DESTINO_LOCAL" 2>"$WORKDIR/openssl.err"; then
  ERRO="$(tail -c 500 "$WORKDIR/openssl.err" 2>/dev/null)"
  rm -f "$DESTINO_LOCAL"
  escrever_status "erro" 0 "Falha ao cifrar o pacote: ${ERRO:-erro desconhecido}"
  exit 1
fi

chown root:www-data "$DESTINO_LOCAL"
chmod 640 "$DESTINO_LOCAL"

TAMANHO_BYTES=$(stat -c '%s' "$DESTINO_LOCAL" 2>/dev/null || echo 0)

ENVIADO_NUVEM=0
if [ -n "$REMOTE" ] && [ -n "$DESTINO_REMOTO" ]; then
  escrever_status "rodando" 90 "Enviando copia para a nuvem..." "$ARQUIVO_FINAL" "$TAMANHO_BYTES" 0

  if rclone copyto "$DESTINO_LOCAL" "${REMOTE}:${DESTINO_REMOTO}/_config_backup/${ARQUIVO_FINAL}" \
      --config /etc/rd-intranet/rclone/rclone.conf 2>"$WORKDIR/rclone.err"; then
    ENVIADO_NUVEM=1
  fi
  # falha ao enviar pra nuvem nao invalida o backup local ja gerado -- so
  # fica registrado enviado_nuvem=0, o admin ainda tem o arquivo pra baixar
fi

logger -t rd-intranet-config-backup "Backup de configuracao gerado com sucesso (execucao ${EXECUCAO_ID}, ${TAMANHO_BYTES} bytes, itens: ${#INCLUIDOS[@]})."

escrever_status "concluido" 100 "Backup gerado com sucesso." "$ARQUIVO_FINAL" "$TAMANHO_BYTES" "$ENVIADO_NUVEM"
exit 0
