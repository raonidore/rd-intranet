#!/bin/bash
# samba_dc_grupo_membro_adicionar_web.sh <grupo> <usuario>

set -u

GRUPO="$1"
USUARIO="$2"

SAIDA=$(samba-tool group addmembers "$GRUPO" "$USUARIO" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "\"" . $argv[1] . "\" adicionado ao grupo \"" . $argv[2] . "\"."]);' -- "$USUARIO" "$GRUPO"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao adicionar membro: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
