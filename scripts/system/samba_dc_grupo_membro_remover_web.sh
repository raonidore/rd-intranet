#!/bin/bash
# samba_dc_grupo_membro_remover_web.sh <grupo> <usuario>

set -u

GRUPO="$1"
USUARIO="$2"

SAIDA=$(samba-tool group removemembers "$GRUPO" "$USUARIO" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "\"" . $argv[1] . "\" removido do grupo \"" . $argv[2] . "\"."]);' -- "$USUARIO" "$GRUPO"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao remover membro: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
