#!/bin/bash
# samba_dc_grupo_criar_web.sh <nome>

set -u

NOME="$1"

if [[ ! "$NOME" =~ ^[a-zA-Z][a-zA-Z0-9._-]{0,63}$ ]]; then
  echo '{"success":false,"message":"Nome de grupo inválido."}'
  exit 1
fi

SAIDA=$(samba-tool group add "$NOME" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Grupo \"" . $argv[1] . "\" criado."]);' -- "$NOME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao criar grupo: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
