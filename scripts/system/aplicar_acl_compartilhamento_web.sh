#!/bin/bash
# aplicar_acl_compartilhamento_web.sh <nome_compartilhamento> <grupo> <caminho> <login:leitura:escrita> [...]
# leitura/escrita = 0 ou 1
#
# Recalcula do zero a ACL de usuarios individuais de um compartilhamento via
# smbcacls (ACL do Windows/Samba, nao POSIX) -- autentica como a conta de
# servico svc_acl_admin (SeDiskOperatorPrivilege + admin users no smb.conf),
# usando o authfile gerado por setup_acl_admin.sh. "-S" substitui a ACL
# inteira do share pelas ACEs passadas (mesmo padrao "recalcula tudo" ja
# usado em salvar_permitidos_web.sh e em
# SambaCompartilhamentoRepository::salvarUsuariosAutorizados).
#
# So 2 niveis reais: leitura (RX) e escrita (+WD). Nao existe "exclusao"
# separada de "escrita" -- testado empiricamente: a lixeira (vfs_recycle,
# sempre ligada) faz o delete-de-dentro-de-.recycle direto no filesystem,
# sem passar pela checagem de ACL do Windows. Ou seja, quem tem escrita ja
# consegue apagar de verdade via a propria lixeira, entao a mascara de
# escrita ja inclui D (delete) para refletir o que de fato acontece.
#
# IMPORTANTE: -S troca a ACL inteira. Por isso o grupo dono do
# compartilhamento sempre entra como primeira ACE, com acesso completo --
# sem isso, os membros do grupo perderiam acesso no primeiro save desta
# tela, mesmo sem nunca terem sido listados aqui. "read only" continua
# sendo reforcado no nivel do smb.conf (share), esta ACL nao substitui isso.
#
# --propagate-inheritance: sem isso, -S só grava a ACL na RAIZ do share --
# as ACEs (OI|CI, herdáveis) só valeriam pra arquivos/pastas criados DEPOIS
# desse ponto. Qualquer coisa que já existia dentro do compartilhamento
# (dados migrados, subpastas antigas) nunca herda a ACE nova, e com "hide
# unreadable = yes" no smb.conf isso não aparece como "acesso negado" --
# o arquivo some da listagem, dando a impressão de pasta vazia. Com essa
# flag, o smbcacls percorre a árvore e aplica a herança nos itens
# existentes também (marcando com a flag (I) de "inherited").
#
# "-S ... --propagate-inheritance" exige que a raiz já esteja marcada como
# "protected" (não herdando de um pai) -- código-fonte do smbcacls
# (source3/utils/smbcacls.c, prepare_inheritance_propagation): se
# SEC_DESC_DACL_PROTECTED não estiver setada, recusa com "Inheritance
# enabled at X, can't apply set operation". A raiz de um share nunca tem
# pai de verdade, mas por padrão nasce "not protected" -- por isso "-I
# remove" roda antes pra marcar/garantir esse estado (idempotente: se já
# estiver protected, ele "falha" com uma mensagem inofensiva de no-op,
# por isso o || true -- só a chamada de verdade, a de baixo, importa pro
# resultado).
#
# PROGRESSO/SEGUNDO PLANO: pra uma árvore grande (milhares de arquivos),
# --propagate-inheritance é uma operação SMB item a item -- pode levar
# minutos, tempo demais pra segurar uma requisição HTTP (foi exatamente o
# que causou "NT_STATUS_CONNECTION_DISCONNECTED" em produção: a conexão
# caiu no meio por causa do tempo, não por um erro de ACL). Este script é
# chamado em segundo plano (LinuxService::executarScriptEmSegundoPlano) e
# escreve o próprio progresso num arquivo de status que o portal consulta
# por polling -- não tem como pedir uma contagem "X de Y" pro smbcacls (ele
# só imprime linha em caso de ERRO, nada em caso de sucesso), então o
# progresso é aproximado contando, no sistema de arquivos local (bem mais
# rápido que perguntar por SMB), quantos itens tiveram o ctime atualizado
# desde o início -- setar a ACL de um item sempre atualiza o ctime dele.

STATUS_DIR="/var/www/rd.intranet/storage/samba_acl_status"
AUTHFILE="/opt/rdtecnologia/scripts/.smbacl_auth"
SHARE="$1"
GRUPO="$2"
CAMINHO="$3"
shift 3

mkdir -p "$STATUS_DIR"
STATUS_FILE="$STATUS_DIR/${SHARE}.json"
LOG_FILE="$STATUS_DIR/${SHARE}.log"

escrever_status() {
  local status="$1" processados="$2" total="$3"
  php -r '
    $status = $argv[1];
    $processados = (int)$argv[2];
    $total = (int)$argv[3];
    $pct = $total > 0 ? (int)round(($processados / $total) * 100) : 100;
    echo json_encode([
        "status" => $status,
        "processados" => $processados,
        "total" => $total,
        "percentual" => min(100, $pct),
        "atualizado_em" => time(),
    ]);
  ' -- "$status" "$processados" "$total" > "$STATUS_FILE"
  chmod 644 "$STATUS_FILE"
}

if [ ! -f "$AUTHFILE" ]; then
  echo "Authfile nao encontrado. Rode setup_acl_admin.sh primeiro." | tee "$LOG_FILE"
  escrever_status "erro" 0 0
  exit 1
fi

if [[ ! "$SHARE" =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "Nome de compartilhamento invalido" | tee "$LOG_FILE"
  escrever_status "erro" 0 0
  exit 1
fi

if [[ ! "$GRUPO" =~ ^[a-z][a-z0-9_-]*$ ]]; then
  echo "Grupo invalido" | tee "$LOG_FILE"
  escrever_status "erro" 0 0
  exit 1
fi

if [ ! -d "$CAMINHO" ]; then
  echo "Caminho nao encontrado no sistema: $CAMINHO" | tee "$LOG_FILE"
  escrever_status "erro" 0 0
  exit 1
fi

ACES="REVISION:1,OWNER:root,GROUP:${GRUPO},ACL:${GRUPO}:ALLOWED/OI|CI/RWD"

for ITEM in "$@"; do
  IFS=':' read -r LOGIN LEITURA ESCRITA <<< "$ITEM"

  if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
    echo "Login invalido: $LOGIN" | tee "$LOG_FILE"
    escrever_status "erro" 0 0
    exit 1
  fi

  MASK=""
  [ "$LEITURA" = "1" ] && MASK="${MASK}RX"
  [ "$ESCRITA" = "1" ] && MASK="${MASK}WD"

  if [ -z "$MASK" ]; then
    continue
  fi

  ACES="${ACES},ACL:${LOGIN}:ALLOWED/OI|CI/${MASK}"
done

TOTAL=$(find "$CAMINHO" 2>/dev/null | wc -l)
INICIO=$(date +%s)

escrever_status "rodando" 0 "$TOTAL"

smbcacls "//localhost/${SHARE}" "" -A "$AUTHFILE" -I remove >/dev/null 2>&1 || true

smbcacls "//localhost/${SHARE}" "" -A "$AUTHFILE" -S "$ACES" --propagate-inheritance > "$LOG_FILE" 2>&1 &
PID_SMBCACLS=$!

while kill -0 "$PID_SMBCACLS" 2>/dev/null; do
  PROCESSADOS=$(find "$CAMINHO" -newerct "@$INICIO" 2>/dev/null | wc -l)
  escrever_status "rodando" "$PROCESSADOS" "$TOTAL"
  sleep 3
done

wait "$PID_SMBCACLS"
CODIGO=$?

PROCESSADOS_FINAL=$(find "$CAMINHO" -newerct "@$INICIO" 2>/dev/null | wc -l)

if [ "$CODIGO" -eq 0 ]; then
  escrever_status "concluido" "$TOTAL" "$TOTAL"
else
  escrever_status "erro" "$PROCESSADOS_FINAL" "$TOTAL"
fi

exit "$CODIGO"
