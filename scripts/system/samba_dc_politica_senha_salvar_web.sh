#!/bin/bash
# samba_dc_politica_senha_salvar_web.sh <complexidade:on|off> <historico> <tam_min> <idade_min> <idade_max> <bloqueio_limite> <bloqueio_duracao> <bloqueio_reset>

set -u

COMPLEXIDADE="$1"
HISTORICO="$2"
TAM_MIN="$3"
IDADE_MIN="$4"
IDADE_MAX="$5"
BLOQ_LIMITE="$6"
BLOQ_DURACAO="$7"
BLOQ_RESET="$8"

if [ "$COMPLEXIDADE" != "on" ] && [ "$COMPLEXIDADE" != "off" ]; then
  echo '{"success":false,"message":"Valor de complexidade inválido."}'
  exit 1
fi

for V in "$HISTORICO" "$TAM_MIN" "$IDADE_MIN" "$IDADE_MAX" "$BLOQ_LIMITE" "$BLOQ_DURACAO" "$BLOQ_RESET"; do
  if [[ ! "$V" =~ ^[0-9]+$ ]]; then
    echo '{"success":false,"message":"Valores numéricos inválidos."}'
    exit 1
  fi
done

SAIDA=$(samba-tool domain passwordsettings set \
  --complexity="$COMPLEXIDADE" \
  --history-length="$HISTORICO" \
  --min-pwd-length="$TAM_MIN" \
  --min-pwd-age="$IDADE_MIN" \
  --max-pwd-age="$IDADE_MAX" \
  --account-lockout-threshold="$BLOQ_LIMITE" \
  --account-lockout-duration="$BLOQ_DURACAO" \
  --account-lockout-reset-count="$BLOQ_RESET" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  echo '{"success":true,"message":"Política de senha do domínio atualizada."}'
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao salvar política de senha: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
