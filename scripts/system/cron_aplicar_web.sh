#!/bin/bash
# cron_aplicar_web.sh <arquivo_tmp>
#
# Instala o conteudo de <arquivo_tmp> em /etc/cron.d/rd-intranet, o arquivo
# unico que a tela Infraestrutura > Cron gerencia por completo (regenerado
# do zero a cada criacao/edicao/exclusao/toggle de job, ver
# CronService::regenerarArquivo()). Nunca mexe em nenhum outro arquivo de
# cron do sistema.
#
# Revalida cada linha aqui, mesmo que o PHP ja tenha validado -- mesmo
# criterio do services_web.sh: um bug/CSRF na tela nao pode fazer o script
# instalar qualquer coisa no cron.d, so o que passa nesta validacao.

set -u

ORIGEM="$1"
DESTINO="/etc/cron.d/rd-intranet"
BACKUP_DIR="/etc/rd-intranet/.cron-backups"

if [ ! -f "$ORIGEM" ]; then
  echo '{"success":false,"message":"Arquivo de origem nao encontrado."}'
  exit 1
fi

CAMPO_RE='^(\*|[0-9]+(-[0-9]+)?)(/[0-9]+)?(,(\*|[0-9]+(-[0-9]+)?)(/[0-9]+)?)*$'
ATALHO_RE='^@(reboot|yearly|annually|monthly|weekly|daily|midnight|hourly)$'
USUARIO_RE='^[a-zA-Z_][a-zA-Z0-9_-]{0,31}$'

while IFS= read -r LINHA; do
  [ -z "$LINHA" ] && continue
  case "$LINHA" in
    '#'*|SHELL=*|PATH=*|MAILTO=*) continue ;;
  esac

  read -r -a CAMPOS <<< "$LINHA"

  if [[ "${CAMPOS[0]:-}" =~ $ATALHO_RE ]]; then
    if [ "${#CAMPOS[@]}" -lt 3 ]; then
      echo "{\"success\":false,\"message\":\"Linha invalida no cron: ${LINHA//\"/\\\"}\"}"
      exit 1
    fi
    USUARIO="${CAMPOS[1]}"
  else
    if [ "${#CAMPOS[@]}" -lt 7 ]; then
      echo "{\"success\":false,\"message\":\"Linha invalida no cron: ${LINHA//\"/\\\"}\"}"
      exit 1
    fi
    for I in 0 1 2 3 4; do
      if ! [[ "${CAMPOS[$I]}" =~ $CAMPO_RE ]]; then
        echo "{\"success\":false,\"message\":\"Campo de agendamento invalido na linha: ${LINHA//\"/\\\"}\"}"
        exit 1
      fi
    done
    USUARIO="${CAMPOS[5]}"
  fi

  if ! [[ "$USUARIO" =~ $USUARIO_RE ]]; then
    echo "{\"success\":false,\"message\":\"Nome de usuario invalido na linha: ${LINHA//\"/\\\"}\"}"
    exit 1
  fi

  if ! id "$USUARIO" >/dev/null 2>&1; then
    echo "{\"success\":false,\"message\":\"Usuario nao existe no sistema: ${USUARIO}\"}"
    exit 1
  fi
done < "$ORIGEM"

mkdir -p "$BACKUP_DIR"
if [ -f "$DESTINO" ]; then
  cp "$DESTINO" "$BACKUP_DIR/rd-intranet.bkp.$(date +%Y%m%d%H%M%S)"
fi

cp "$ORIGEM" "$DESTINO"
chown root:root "$DESTINO"
chmod 644 "$DESTINO"

# diretorio de logs por job, 1777 (igual /tmp) pra qualquer usuario
# configurado num job conseguir gravar o proprio arquivo de saida
mkdir -p /var/log/rd-intranet-cron
chmod 1777 /var/log/rd-intranet-cron

echo '{"success":true,"message":"Cron do sistema atualizado com sucesso."}'
