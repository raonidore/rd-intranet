#!/bin/bash
# samba_dc_gpo_excluir_web.sh <guid>

set -u

GUID="$1"

if [[ ! "$GUID" =~ ^\{[0-9A-Fa-f-]+\}$ ]]; then
  echo '{"success":false,"message":"GUID de GPO inválido."}'
  exit 1
fi

SAIDA=$(samba-tool gpo del "$GUID" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  echo '{"success":true,"message":"GPO excluída."}'
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao excluir GPO: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
