#!/bin/bash
# samba_dc_usuario_detalhes_web.sh <username>
#
# Detalhe de um usuario do dominio -- parseia a saida de "samba-tool user
# show" (formato LDIF-like, "campo: valor" por linha) nos poucos campos
# uteis pra tela de detalhe. Tolerante a campo ausente (pwdLastSet/
# lockoutTime nem sempre aparecem).

set -u

USERNAME="$1"

SAIDA=$(samba-tool user show "$USERNAME" 2>&1)
if [ $? -ne 0 ]; then
  php -r 'echo json_encode(["success" => false, "message" => "Usuário não encontrado ou falha ao consultar: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
  exit 1
fi

UAC=$(printf '%s' "$SAIDA" | grep -i '^userAccountControl:' | awk '{print $2}')
UAC=${UAC:-512}
LOCKOUT=$(printf '%s' "$SAIDA" | grep -i '^lockoutTime:' | awk '{print $2}')
LOCKOUT=${LOCKOUT:-0}
PWD_LAST_SET=$(printf '%s' "$SAIDA" | grep -i '^pwdLastSet:' | cut -d: -f2- | sed 's/^ *//')
MEMBER_OF=$(printf '%s' "$SAIDA" | grep -i '^memberOf:' | sed -E 's/^memberOf: *CN=([^,]+),.*/\1/i')

HABILITADO="1"
if (( UAC & 2 )); then
  HABILITADO="0"
fi

BLOQUEADO="0"
if [ "$LOCKOUT" != "0" ] && [ -n "$LOCKOUT" ]; then
  BLOQUEADO="1"
fi

GRUPOS_ARGS=()
while IFS= read -r G; do
  [ -z "$G" ] && continue
  GRUPOS_ARGS+=("$G")
done <<< "$MEMBER_OF"

php -r '
    $grupos = array_values(array_filter(array_slice($argv, 5), fn($g) => $g !== ""));
    echo json_encode([
        "username" => $argv[1],
        "habilitado" => $argv[2] === "1",
        "bloqueado" => $argv[3] === "1",
        "senha_definida_em" => $argv[4] !== "" ? $argv[4] : null,
        "grupos" => $grupos,
    ]);
' -- "$USERNAME" "$HABILITADO" "$BLOQUEADO" "$PWD_LAST_SET" "${GRUPOS_ARGS[@]:-}"
