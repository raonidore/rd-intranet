#!/bin/bash
# backup_ler_config_web.sh <remote>
#
# Imprime a secao [<remote>] do rclone.conf (chave = valor, uma por
# linha). Usado so pra reler o token do Google Drive depois de uma
# execucao -- o rclone renova (e reescreve no rclone.conf) o access_token
# a cada uso, e o portal precisa capturar essa renovacao pra re-cifrar e
# salvar em backup_destinos, senao o token cifrado no banco fica
# desatualizado e a proxima execucao falha quando o antigo expirar (ver
# BackupService::atualizarTokenDriveAposExecucao()).

set -u

REMOTE="$1"
CONFIG="/etc/rd-intranet/rclone/rclone.conf"

if [[ ! "$REMOTE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo "Remote invalido" >&2
  exit 1
fi

if [ ! -f "$CONFIG" ]; then
  exit 1
fi

rclone config show "$REMOTE" --config "$CONFIG"
