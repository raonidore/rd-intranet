#!/bin/bash
# samba_dc_usuario_desbloquear_web.sh <username>
#
# Desbloqueia uma conta travada por excesso de tentativas de senha erradas
# -- diferente de ativar/desativar (que e uma decisao deliberada do admin),
# isso e o "lockoutTime" que o proprio AD seta sozinho.

set -u

USERNAME="$1"

SAIDA=$(samba-tool user unlock "$USERNAME" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Usuário \"" . $argv[1] . "\" desbloqueado."]);' -- "$USERNAME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao desbloquear usuário: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
