#!/bin/bash
# samba_dc_gpo_vincular_web.sh <guid> <container_dn>
#
# <container_dn> e o DN completo de uma OU (como devolvido por "samba-tool
# ou list") ou o DN raiz do dominio, pra vincular a GPO em todo o dominio.

set -u

GUID="$1"
CONTAINER_DN="$2"

if [[ ! "$GUID" =~ ^\{[0-9A-Fa-f-]+\}$ ]]; then
  echo '{"success":false,"message":"GUID de GPO inválido."}'
  exit 1
fi

SAIDA=$(samba-tool gpo setlink "$CONTAINER_DN" "$GUID" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  echo '{"success":true,"message":"GPO vinculada."}'
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao vincular GPO: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
