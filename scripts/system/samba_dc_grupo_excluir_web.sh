#!/bin/bash
# samba_dc_grupo_excluir_web.sh <nome>

set -u

NOME="$1"

SAIDA=$(samba-tool group delete "$NOME" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Grupo \"" . $argv[1] . "\" excluído."]);' -- "$NOME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao excluir grupo: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
