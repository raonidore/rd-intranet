#!/bin/bash
# samba_dc_usuario_excluir_web.sh <username>

set -u

USERNAME="$1"

SAIDA=$(samba-tool user delete "$USERNAME" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Usuário \"" . $argv[1] . "\" excluído."]);' -- "$USERNAME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao excluir usuário: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
