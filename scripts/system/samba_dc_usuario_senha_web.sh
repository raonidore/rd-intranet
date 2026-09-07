#!/bin/bash
# samba_dc_usuario_senha_web.sh <username>
#
# Reseta a senha de um usuario do dominio. Senha nova via STDIN (uma
# linha), mesmo mecanismo de samba_dc_usuario_criar_web.sh.

set -u

USERNAME="$1"

read -r SENHA

if [ -z "$SENHA" ]; then
  echo '{"success":false,"message":"Senha não informada."}'
  exit 1
fi

SAIDA=$(printf '%s\n%s\n' "$SENHA" "$SENHA" | samba-tool user setpassword "$USERNAME" 2>&1)
CODIGO=$?
SENHA=""

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Senha de \"" . $argv[1] . "\" redefinida."]);' -- "$USERNAME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao redefinir senha: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
