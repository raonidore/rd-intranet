#!/bin/bash
# samba_dc_usuario_criar_web.sh <username> <nome_completo>
#
# Cria um usuario no dominio. Senha chega via STDIN (uma linha), nunca por
# argv -- lida com "read" e repassada ao samba-tool via "printf" (builtin
# do bash, nao um processo novo -- nunca aparece em "ps aux").

set -u

USERNAME="$1"
NOME_COMPLETO="$2"

if [[ ! "$USERNAME" =~ ^[a-zA-Z][a-zA-Z0-9._-]{0,19}$ ]]; then
  echo '{"success":false,"message":"Nome de usuário inválido."}'
  exit 1
fi

read -r SENHA

if [ -z "$SENHA" ]; then
  echo '{"success":false,"message":"Senha não informada."}'
  exit 1
fi

SAIDA=$(printf '%s\n%s\n' "$SENHA" "$SENHA" | samba-tool user create "$USERNAME" --given-name="$NOME_COMPLETO" 2>&1)
CODIGO=$?
SENHA=""

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Usuário \"" . $argv[1] . "\" criado."]);' -- "$USERNAME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao criar usuário: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
