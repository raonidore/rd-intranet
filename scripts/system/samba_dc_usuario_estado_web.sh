#!/bin/bash
# samba_dc_usuario_estado_web.sh <username> <ativar|desativar>

set -u

USERNAME="$1"
ACAO="$2"

if [ "$ACAO" != "ativar" ] && [ "$ACAO" != "desativar" ]; then
  echo '{"success":false,"message":"Ação inválida."}'
  exit 1
fi

if [ "$ACAO" = "ativar" ]; then
  SAIDA=$(samba-tool user enable "$USERNAME" 2>&1)
else
  SAIDA=$(samba-tool user disable "$USERNAME" 2>&1)
fi
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Usuário \"" . $argv[1] . "\" " . ($argv[2] === "ativar" ? "ativado" : "desativado") . "."]);' -- "$USERNAME" "$ACAO"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
