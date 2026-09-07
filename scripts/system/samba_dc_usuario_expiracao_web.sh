#!/bin/bash
# samba_dc_usuario_expiracao_web.sh <username> <dias|nunca>

set -u

USERNAME="$1"
DIAS="$2"

if [ "$DIAS" = "nunca" ]; then
  SAIDA=$(samba-tool user setexpiry "$USERNAME" --noexpiry 2>&1)
elif [[ "$DIAS" =~ ^[0-9]+$ ]]; then
  SAIDA=$(samba-tool user setexpiry "$USERNAME" --days="$DIAS" 2>&1)
else
  echo '{"success":false,"message":"Valor de expiração inválido."}'
  exit 1
fi
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Expiração de senha de \"" . $argv[1] . "\" atualizada."]);' -- "$USERNAME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao atualizar expiração: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
