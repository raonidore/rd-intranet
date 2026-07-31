#!/bin/bash
# config_backup_restaurar_web.sh <execucao_id> <caminho_tar_enc>
#
# Restaura um pacote gerado por config_backup_gerar_web.sh: decifra, importa
# o dump do banco e devolve os arquivos criticos do SO pros caminhos reais.
# Cenario esperado: servidor recem-reinstalado (install.sh ja rodou, banco
# recem-migrado e praticamente vazio) -- ver Sistema > Configuracoes >
# Restaurar. Roda como script privilegiado (sudo), em segundo plano
# (LinuxService::executarScriptEmSegundoPlanoComEntrada), senha via STDIN.
#
# So cobre os passos que exigem privilegio de root (chave de criptografia,
# import do banco, arquivos do SO, reinicio de servicos). Os passos que
# regeneram configuracao a PARTIR do banco ja restaurado (shares.conf,
# cron.d, firewall, rclone.conf, Cloudflare Tunnel) rodam depois, em PHP
# puro (ConfigRestauracaoService::finalizar()), reaproveitando os mesmos
# metodos de "aplicar" que cada modulo ja usa -- nao duplicados aqui.
#
# Nenhuma linha de historico e gravada no banco (o proprio import do dump
# substituiria/apagaria essa linha no mesmo instante) -- a trilha durável
# fica no syslog (logger) + no arquivo de status.

set -uo pipefail

STATUS_DIR="/var/www/rd.intranet/storage/config_restore_status"
mkdir -p "$STATUS_DIR"

EXECUCAO_ID="$1"
ARQUIVO_ENC="$2"

STATUS_FILE="$STATUS_DIR/${EXECUCAO_ID}.json"
ITER_PBKDF2=200000

escrever_status() {
  local status="$1" percentual="$2" mensagem="${3:-}"
  php -r '
    echo json_encode([
        "status" => $argv[1],
        "percentual" => (int)$argv[2],
        "mensagem" => $argv[3],
        "atualizado_em" => time(),
    ]);
  ' -- "$status" "$percentual" "$mensagem" > "$STATUS_FILE"
  chmod 644 "$STATUS_FILE"
}

if [[ ! "$EXECUCAO_ID" =~ ^[0-9a-f]+$ ]]; then
  echo "ID de execucao invalido" >&2
  exit 1
fi

if [ ! -f "$ARQUIVO_ENC" ]; then
  escrever_status "erro" 0 "Arquivo de backup nao encontrado no servidor."
  exit 1
fi

SENHA="$(cat -)"
if [ -z "$SENHA" ]; then
  escrever_status "erro" 0 "Senha de criptografia vazia."
  exit 1
fi

WORKDIR="/root/.rd_config_restore/${EXECUCAO_ID}"
rm -rf "$WORKDIR"
mkdir -p "$WORKDIR"
chmod 700 "$WORKDIR"

limpar() {
  rm -rf "$WORKDIR"
}
trap limpar EXIT

escrever_status "rodando" 5 "Decifrando pacote..."

if ! printf '%s' "$SENHA" | openssl enc -d -aes-256-cbc -pbkdf2 -iter "$ITER_PBKDF2" -pass stdin \
    -in "$ARQUIVO_ENC" -out "$WORKDIR/pacote.tar" 2>"$WORKDIR/openssl.err"; then
  escrever_status "erro" 0 "Senha incorreta ou arquivo corrompido -- nao foi possivel decifrar o pacote."
  exit 1
fi

if ! tar -tf "$WORKDIR/pacote.tar" > "$WORKDIR/listagem.txt" 2>"$WORKDIR/tar_list.err"; then
  escrever_status "erro" 0 "Pacote decifrado, mas o conteudo nao e um tar valido (arquivo corrompido?)."
  exit 1
fi

if ! grep -qx "manifest.json" "$WORKDIR/listagem.txt" || ! grep -qx "database.sql" "$WORKDIR/listagem.txt"; then
  escrever_status "erro" 0 "Pacote nao contem manifest.json/database.sql -- nao parece ser um backup de configuracao valido."
  exit 1
fi

mkdir -p "$WORKDIR/extraido"
tar -xf "$WORKDIR/pacote.tar" -C "$WORKDIR/extraido" manifest.json database.sql

escrever_status "rodando" 15 "Restaurando chave de criptografia..."

# db_secret.key restaurado ANTES do import do banco -- os campos cifrados
# no dump so decifram corretamente com a chave que estava em uso quando o
# backup foi gerado, nao com a que o install.sh acabou de criar do zero.
if grep -qx "etc/rd-intranet/db_secret.key" "$WORKDIR/listagem.txt"; then
  mkdir -p /etc/rd-intranet
  tar -xf "$WORKDIR/pacote.tar" -C / etc/rd-intranet/db_secret.key
  chown root:www-data /etc/rd-intranet/db_secret.key
  chmod 640 /etc/rd-intranet/db_secret.key
else
  escrever_status "erro" 0 "Pacote nao contem a chave de criptografia (db_secret.key) -- restauracao abortada pra nao deixar os segredos do banco ilegiveis."
  exit 1
fi

escrever_status "rodando" 35 "Importando banco de dados..."

DB_CONF="/var/www/rd.intranet/app/Config/database.php"
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

if ! MYSQL_PWD="$DB_SENHA" mysql -h"$DB_HOST" -u"$DB_USUARIO" "$DB_NOME" < "$WORKDIR/extraido/database.sql" 2>"$WORKDIR/mysql.err"; then
  ERRO="$(tail -c 500 "$WORKDIR/mysql.err" 2>/dev/null)"
  escrever_status "erro" 0 "Falha ao importar o banco de dados: ${ERRO:-erro desconhecido}"
  exit 1
fi

escrever_status "rodando" 60 "Restaurando arquivos de configuracao do sistema..."

# tabela de permissoes por caminho -- mesmo raciocinio de defesa em
# profundidade dos demais scripts _web.sh: cada arquivo restaurado no dono/
# permissao que o respectivo modulo espera, nao o padrao do tar (que
# preserva o uid/gid numerico de quando foi gerado, que pode nao bater com
# este servidor).
restaurar_item() {
  local rel="$1" dono="$2" modo="$3"
  if grep -qx "$rel" "$WORKDIR/listagem.txt"; then
    mkdir -p "$(dirname "/$rel")"
    tar -xf "$WORKDIR/pacote.tar" -C / "$rel"
    chown -R "$dono" "/$rel" 2>/dev/null
    chmod -R "$modo" "/$rel" 2>/dev/null
    return 0
  fi
  return 1
}

RESTAURADOS=()
FALHAS_SERVICO=()

restaurar_item "etc/rd-intranet/rotas-extras.conf" "root:root" "644" && RESTAURADOS+=("rotas-extras.conf")
restaurar_item "etc/rd-intranet/.certificado-tipo" "root:www-data" "640" && RESTAURADOS+=(".certificado-tipo")
restaurar_item "etc/rd-intranet/.certificado-dominio" "root:www-data" "640" && RESTAURADOS+=(".certificado-dominio")
restaurar_item "etc/samba/smb.conf" "root:root" "644" && RESTAURADOS+=("smb.conf")
restaurar_item "var/lib/samba/private/passdb.tdb" "root:root" "600" && RESTAURADOS+=("passdb.tdb")
restaurar_item "etc/openvpn/server/easy-rsa/pki" "root:root" "700" && RESTAURADOS+=("PKI OpenVPN/IKEv2")
restaurar_item "etc/netplan/90-rd-intranet.yaml" "root:root" "600" && RESTAURADOS+=("netplan")
restaurar_item "etc/ssl/rd-intranet/atual.crt" "root:root" "644" && RESTAURADOS+=("certificado HTTPS")
restaurar_item "etc/ssl/rd-intranet/atual.key" "root:root" "600" && RESTAURADOS+=("chave HTTPS")
restaurar_item "etc/letsencrypt" "root:root" "700" && RESTAURADOS+=("Let's Encrypt")
restaurar_item "opt/meshcentral/meshcentral-data" "root:root" "750" && RESTAURADOS+=("MeshCentral")

for REL in $(grep '^etc/wireguard/.*\.conf$' "$WORKDIR/listagem.txt" 2>/dev/null || true); do
  restaurar_item "$REL" "root:root" "600" && RESTAURADOS+=("$(basename "$REL")")
done

escrever_status "rodando" 85 "Reiniciando servicos..."

if [ -e /var/lib/samba/private/passdb.tdb ]; then
  systemctl reload smbd >/dev/null 2>&1 || FALHAS_SERVICO+=("smbd")
fi
if [ -d /etc/openvpn/server/easy-rsa/pki ]; then
  systemctl restart openvpn-server@server >/dev/null 2>&1 || true
fi
if [ -f /etc/ipsec.conf ]; then
  systemctl restart strongswan-starter >/dev/null 2>&1 || systemctl restart strongswan >/dev/null 2>&1 || true
fi
for CONF in /etc/wireguard/*.conf; do
  [ -e "$CONF" ] || continue
  IFACE="$(basename "$CONF" .conf)"
  wg-quick down "$IFACE" >/dev/null 2>&1 || true
  wg-quick up "$IFACE" >/dev/null 2>&1 || FALHAS_SERVICO+=("wg-quick $IFACE")
done

MENSAGEM="Restauracao concluida. Itens restaurados: $(IFS=', '; echo "${RESTAURADOS[*]}")."
if [ "${#FALHAS_SERVICO[@]}" -gt 0 ]; then
  MENSAGEM="${MENSAGEM} Falha ao reiniciar (melhor esforco, confira manualmente): $(IFS=', '; echo "${FALHAS_SERVICO[*]}")."
fi

logger -t rd-intranet-config-backup "Restauracao de configuracao concluida (execucao ${EXECUCAO_ID}). ${MENSAGEM}"

escrever_status "concluido" 100 "$MENSAGEM"
exit 0
