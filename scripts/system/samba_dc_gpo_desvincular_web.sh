#!/bin/bash
# samba_dc_gpo_desvincular_web.sh <guid> <container_dn>

set -u

GUID="$1"
CONTAINER_DN="$2"

if [[ ! "$GUID" =~ ^\{[0-9A-Fa-f-]+\}$ ]]; then
  echo '{"success":false,"message":"GUID de GPO inválido."}'
  exit 1
fi

SAIDA=$(samba-tool gpo dellink "$CONTAINER_DN" "$GUID" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  echo '{"success":true,"message":"Vínculo removido."}'
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao desvincular GPO: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
