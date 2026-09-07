#!/bin/bash
# samba_dc_ou_criar_web.sh <nome>
#
# Cria uma OU de nivel unico (ex: "Financeiro") direto na raiz do dominio.
# OU aninhada (OU dentro de OU) fica fora desta versao -- exigiria um
# seletor de arvore na tela, escopo maior; se aparecer necessidade real
# depois, o comando aceita "OU=Sub,OU=Pai" sem mudar nada aqui, so a UI
# precisaria de um campo "OU pai".

set -u

NOME="$1"

REGEX_NOME='^[a-zA-Z][a-zA-Z0-9 ._-]{0,63}$'
if [[ ! "$NOME" =~ $REGEX_NOME ]]; then
  echo '{"success":false,"message":"Nome de OU inválido."}'
  exit 1
fi

SAIDA=$(samba-tool ou create "OU=${NOME}" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "OU \"" . $argv[1] . "\" criada."]);' -- "$NOME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao criar OU: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
