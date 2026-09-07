#!/bin/bash
# samba_dc_computador_excluir_web.sh <nome>
#
# Remove um computador do dominio (ex: maquina desativada/trocada) --
# equivalente a "desingressar" a maquina do lado do AD; a propria maquina
# nao e avisada, so o objeto no dominio some.

set -u

NOME="$1"

SAIDA=$(samba-tool computer delete "$NOME" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "Computador \"" . $argv[1] . "\" removido do domínio."]);' -- "$NOME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao remover computador: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
