#!/bin/bash
# backup_listar_versoes_web.sh <remote> <destino_remoto> <nivel> [<compartilhamento>] [<timestamp>] [<subpath>]
#
# Navegacao ao vivo pela pasta .versoes/ do bucket, direto do provedor de
# nuvem -- sem depender do historico gravado em backup_execucao_arquivos
# (que so existe pra execucoes rodadas depois dessa funcionalidade). Cobre
# QUALQUER arquivo ja enviado em qualquer epoca, pro "Explorador de
# Versoes" (Backup > Explorador).
#
# nivel=compartilhamentos: lista as subpastas de .versoes/ (cada uma e um
#   compartilhamento que ja teve pelo menos uma versao arquivada).
# nivel=datas: lista as pastas de timestamp dentro de .versoes/<compart>/
#   (cada uma e uma execucao de backup ou uma restauracao manual).
# nivel=itens: lista arquivos/pastas dentro de .versoes/<compart>/<timestamp>/<subpath>
#   (navegacao tipo gerenciador de arquivos, um nivel por vez).
# nivel=atual: lista arquivos/pastas dentro de <compart>/<subpath> -- a
#   copia ATIVA/atual na nuvem (fora de .versoes/), refletindo o que foi
#   enviado no ultimo backup bem-sucedido. <timestamp> nao se aplica aqui,
#   mas continua ocupando a posicao 5 (vazio) pra manter os argumentos
#   posicionais previsiveis com o nivel=itens.
#
# Todas as saidas sao JSON. Falha ao listar (pasta inexistente, sem
# nenhuma versao ainda) sempre devolve um array vazio, nunca erro --
# "nenhum resultado" e um estado normal aqui, nao uma falha.

set -uo pipefail

REMOTE="$1"
DESTINO_REMOTO="$2"
NIVEL="$3"
COMPARTILHAMENTO="${4:-}"
TIMESTAMP_VERSAO="${5:-}"
SUBPATH="${6:-}"

CONFIG="/etc/rd-intranet/rclone/rclone.conf"

if [ ! -f "$CONFIG" ]; then
  echo '{"error":"Configuracao de backup ainda nao foi aplicada."}'
  exit 1
fi

if [[ ! "$REMOTE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo '{"error":"Destino de backup invalido."}'
  exit 1
fi

if [ -n "$DESTINO_REMOTO" ]; then
  ALVO_BASE="${REMOTE}:${DESTINO_REMOTO}"
else
  ALVO_BASE="${REMOTE}:"
fi

case "$NIVEL" in
  compartilhamentos)
    rclone lsf --dirs-only --config "$CONFIG" "${ALVO_BASE%/}/.versoes/" 2>/dev/null | \
      python3 -c "
import sys, json
nomes = [l.strip().rstrip('/') for l in sys.stdin if l.strip()]
print(json.dumps(sorted(nomes)))
"
    ;;

  datas)
    if [[ ! "$COMPARTILHAMENTO" =~ ^[A-Za-z0-9_-]+$ ]]; then
      echo '{"error":"Compartilhamento invalido."}'
      exit 1
    fi
    rclone lsf --dirs-only --config "$CONFIG" "${ALVO_BASE%/}/.versoes/${COMPARTILHAMENTO}/" 2>/dev/null | \
      python3 -c "
import sys, json
nomes = [l.strip().rstrip('/') for l in sys.stdin if l.strip()]
print(json.dumps(sorted(nomes, reverse=True)))
"
    ;;

  itens)
    if [[ ! "$COMPARTILHAMENTO" =~ ^[A-Za-z0-9_-]+$ ]]; then
      echo '{"error":"Compartilhamento invalido."}'
      exit 1
    fi
    if [[ ! "$TIMESTAMP_VERSAO" =~ ^(restauracao_)?[0-9]{8}_[0-9]{6}$ ]]; then
      echo '{"error":"Data de versao invalida."}'
      exit 1
    fi
    if echo "$SUBPATH" | grep -qE '(^|/)\.\.(/|$)'; then
      echo '{"error":"Caminho invalido."}'
      exit 1
    fi

    ALVO="${ALVO_BASE%/}/.versoes/${COMPARTILHAMENTO}/${TIMESTAMP_VERSAO}"
    [ -n "$SUBPATH" ] && ALVO="${ALVO}/${SUBPATH}"

    SAIDA=$(rclone lsjson --config "$CONFIG" "$ALVO" 2>/dev/null)
    echo "${SAIDA:-[]}"
    ;;

  atual)
    if [[ ! "$COMPARTILHAMENTO" =~ ^[A-Za-z0-9_-]+$ ]]; then
      echo '{"error":"Compartilhamento invalido."}'
      exit 1
    fi
    if echo "$SUBPATH" | grep -qE '(^|/)\.\.(/|$)'; then
      echo '{"error":"Caminho invalido."}'
      exit 1
    fi

    ALVO="${ALVO_BASE%/}/${COMPARTILHAMENTO}"
    [ -n "$SUBPATH" ] && ALVO="${ALVO}/${SUBPATH}"

    SAIDA=$(rclone lsjson --config "$CONFIG" "$ALVO" 2>/dev/null)
    echo "${SAIDA:-[]}"
    ;;

  *)
    echo '{"error":"Nivel invalido."}'
    exit 1
    ;;
esac
