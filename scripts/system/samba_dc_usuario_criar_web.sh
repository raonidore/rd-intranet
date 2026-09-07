#!/bin/bash
# samba_dc_usuario_criar_web.sh <username> <nome_completo> [email] [telefone] [descricao]
#
# Cria um usuario no dominio. Senha chega via STDIN (uma linha), nunca por
# argv -- lida com "read" e repassada ao samba-tool via "printf" (builtin
# do bash, nao um processo novo -- nunca aparece em "ps aux"). email/
# telefone/descricao sao opcionais (string vazia = nao passa a flag).

set -u

USERNAME="$1"
NOME_COMPLETO="$2"
EMAIL="${3:-}"
TELEFONE="${4:-}"
DESCRICAO="${5:-}"

if [[ ! "$USERNAME" =~ ^[a-zA-Z][a-zA-Z0-9._-]{0,19}$ ]]; then
  echo '{"success":false,"message":"Nome de usuário inválido."}'
  exit 1
fi

read -r SENHA

if [ -z "$SENHA" ]; then
  echo '{"success":false,"message":"Senha não informada."}'
  exit 1
fi

ARGS_EXTRA=(--given-name="$NOME_COMPLETO")
[ -n "$EMAIL" ] && ARGS_EXTRA+=("--mail-address=$EMAIL")
[ -n "$TELEFONE" ] && ARGS_EXTRA+=("--telephone-number=$TELEFONE")
[ -n "$DESCRICAO" ] && ARGS_EXTRA+=("--description=$DESCRICAO")

SAIDA=$(printf '%s\n%s\n' "$SENHA" "$SENHA" | samba-tool user create "$USERNAME" "${ARGS_EXTRA[@]}" 2>&1)
CODIGO=$?
SENHA=""

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Usuário \"" . $argv[1] . "\" criado."]);' -- "$USERNAME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao criar usuário: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
