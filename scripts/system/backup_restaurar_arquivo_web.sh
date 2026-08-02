#!/bin/bash
# backup_restaurar_arquivo_web.sh <remote> <destino_remoto> <compartilhamento> <timestamp_versao> <caminho_relativo> <modo> <caminho_local_base> [<caminho_saida_temp>]
#
# Recupera uma versao arquivada em .versoes/<compartilhamento>/<timestamp>/
# (a mesma pasta que backup_executar_web.sh usa via --backup-dir) de volta
# pro compartilhamento Samba -- sem precisar do console do provedor de
# nuvem nem de acesso SSH.
#
# modo=baixar: copia a versao remota pra <caminho_saida_temp> (arquivo
#   temporario local), pro PHP servir como download via passthru/readfile
#   -- nao mexe em nada dentro do compartilhamento.
# modo=restaurar: escreve DIRETO no compartilhamento (sobrescrevendo o que
#   tiver la agora). Antes de sobrescrever, se ja existir um arquivo no
#   destino, ele e enviado pra .versoes/<compartilhamento>/restauracao_<agora>/
#   -- mesma filosofia de "nunca perde nada" do backup em si: restaurar a
#   versao errada por engano tambem fica desfazivel.

set -uo pipefail

REMOTE="$1"
DESTINO_REMOTO="$2"
COMPARTILHAMENTO="$3"
TIMESTAMP_VERSAO="$4"
CAMINHO_RELATIVO="$5"
MODO="$6"
CAMINHO_LOCAL_BASE="$7"
CAMINHO_SAIDA_TEMP="${8:-}"

CONFIG="/etc/rd-intranet/rclone/rclone.conf"

if [ ! -f "$CONFIG" ]; then
  echo '{"success":false,"message":"Configuracao de backup ainda nao foi aplicada."}'
  exit 1
fi

if [[ ! "$REMOTE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo '{"success":false,"message":"Destino de backup invalido."}'
  exit 1
fi

if [[ ! "$COMPARTILHAMENTO" =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo '{"success":false,"message":"Compartilhamento invalido."}'
  exit 1
fi

if [[ ! "$TIMESTAMP_VERSAO" =~ ^[0-9]{8}_[0-9]{6}$ ]]; then
  echo '{"success":false,"message":"Timestamp de versao invalido."}'
  exit 1
fi

# mesma checagem por SEGMENTO usada em todos os scripts de arquivo do
# Samba -- ".." so e rejeitado quando aparece isolado entre barras/pontas,
# nao qualquer ocorrencia (nomes com reticencias sao legitimos)
if echo "$CAMINHO_RELATIVO" | grep -qE '(^|/)\.\.(/|$)'; then
  echo '{"success":false,"message":"Caminho de arquivo invalido."}'
  exit 1
fi

if [ -n "$DESTINO_REMOTO" ]; then
  ALVO_BASE="${REMOTE}:${DESTINO_REMOTO}"
else
  ALVO_BASE="${REMOTE}:"
fi

CAMINHO_REMOTO_VERSAO="${ALVO_BASE%/}/.versoes/${COMPARTILHAMENTO}/${TIMESTAMP_VERSAO}/${CAMINHO_RELATIVO}"

case "$MODO" in
  baixar)
    if [ -z "$CAMINHO_SAIDA_TEMP" ]; then
      echo '{"success":false,"message":"Caminho de saida nao informado."}'
      exit 1
    fi
    if ! rclone copyto "$CAMINHO_REMOTO_VERSAO" "$CAMINHO_SAIDA_TEMP" --config "$CONFIG" 2>/tmp/rd_backup_restaurar_err_$$; then
      ERRO="$(tail -c 400 /tmp/rd_backup_restaurar_err_$$ 2>/dev/null)"
      rm -f /tmp/rd_backup_restaurar_err_$$
      echo "{\"success\":false,\"message\":\"Falha ao buscar a versao no provedor de nuvem: ${ERRO:-erro desconhecido}\"}"
      exit 1
    fi
    rm -f /tmp/rd_backup_restaurar_err_$$
    echo '{"success":true,"message":"Versao recuperada."}'
    ;;

  restaurar)
    # realpath + startswith -- mesma defesa em profundidade dos demais
    # scripts de arquivo do Samba, mesmo o caminho ja vindo validado do banco
    DESTINO_LOCAL=$(python3 -c "
import os, sys
base, rel = sys.argv[1], sys.argv[2]
p = os.path.normpath(os.path.join(base, rel))
print(p if (p == base or p.startswith(base + os.sep)) else '')
" "$CAMINHO_LOCAL_BASE" "$CAMINHO_RELATIVO")

    if [ -z "$DESTINO_LOCAL" ]; then
      echo '{"success":false,"message":"Caminho de destino invalido."}'
      exit 1
    fi

    if [ -f "$DESTINO_LOCAL" ]; then
      TIMESTAMP_SEGURANCA="restauracao_$(date +%Y%m%d_%H%M%S)"
      rclone copyto "$DESTINO_LOCAL" "${ALVO_BASE%/}/.versoes/${COMPARTILHAMENTO}/${TIMESTAMP_SEGURANCA}/${CAMINHO_RELATIVO}" \
        --config "$CONFIG" 2>/dev/null
      # falha ao arquivar a versao atual nao impede a restauracao -- mas
      # sem essa copia de seguranca, avisamos explicitamente na mensagem
      if [ $? -ne 0 ]; then
        AVISO_SEGURANCA=" (atencao: nao foi possivel arquivar a versao atual antes de sobrescrever)"
      fi
    fi

    mkdir -p "$(dirname "$DESTINO_LOCAL")"

    if ! rclone copyto "$CAMINHO_REMOTO_VERSAO" "$DESTINO_LOCAL" --config "$CONFIG" 2>/tmp/rd_backup_restaurar_err_$$; then
      ERRO="$(tail -c 400 /tmp/rd_backup_restaurar_err_$$ 2>/dev/null)"
      rm -f /tmp/rd_backup_restaurar_err_$$
      echo "{\"success\":false,\"message\":\"Falha ao restaurar: ${ERRO:-erro desconhecido}\"}"
      exit 1
    fi
    rm -f /tmp/rd_backup_restaurar_err_$$
    chmod 660 "$DESTINO_LOCAL" 2>/dev/null

    echo "{\"success\":true,\"message\":\"Arquivo restaurado com sucesso.${AVISO_SEGURANCA:-}\"}"
    ;;

  *)
    echo '{"success":false,"message":"Modo invalido."}'
    exit 1
    ;;
esac
