#!/bin/bash
# samba_dc_gpo_criar_web.sh <nome>
#
# Cria o objeto/esqueleto da GPO no dominio + SYSVOL. O CONTEUDO da
# politica (as configuracoes de verdade) precisa ser editado depois via
# GPMC a partir de uma estacao Windows -- nao existe comando de CLI pra
# isso, e reimplementar o formato Registry.pol/GPP do zero foge muito do
# escopo deste sistema.

set -u

NOME="$1"

SAIDA=$(samba-tool gpo create "$NOME" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "GPO \"" . $argv[1] . "\" criada. Edite o conteúdo da política pelo GPMC a partir de uma estação Windows."]);' -- "$NOME"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao criar GPO: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
