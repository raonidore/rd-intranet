#!/bin/bash
# cron_executar_web.sh <usuario> <comando_base64>
#
# Roda imediatamente (fora do agendamento) o comando de um job de cron, pra
# o admin testar pela tela sem esperar o proximo disparo. O comando chega
# em base64 pra nao ter que lidar com aspas/quebras vindas do POST.

set -u

USUARIO="$1"
COMANDO_B64="$2"

if ! [[ "$USUARIO" =~ ^[a-zA-Z_][a-zA-Z0-9_-]{0,31}$ ]]; then
  echo '{"success":false,"output":"Usuario invalido."}'
  exit 1
fi

if ! id "$USUARIO" >/dev/null 2>&1; then
  echo '{"success":false,"output":"Usuario nao existe no sistema."}'
  exit 1
fi

COMANDO="$(echo "$COMANDO_B64" | base64 -d 2>/dev/null)"

if [ -z "$COMANDO" ]; then
  echo '{"success":false,"output":"Comando vazio ou base64 invalido."}'
  exit 1
fi

SAIDA_FILE="/tmp/rd_cron_run_$$"
runuser -u "$USUARIO" -- bash -c "$COMANDO" >"$SAIDA_FILE" 2>&1
STATUS=$?

SAIDA="$(cat "$SAIDA_FILE")"
rm -f "$SAIDA_FILE"

SAIDA_JSON="\"$(printf '%s' "$SAIDA" | sed 's/\\/\\\\/g; s/"/\\"/g' | tr '\n' ' ')\""

if [ "$STATUS" -eq 0 ]; then
  echo "{\"success\":true,\"output\":${SAIDA_JSON}}"
else
  echo "{\"success\":false,\"output\":${SAIDA_JSON}}"
fi
