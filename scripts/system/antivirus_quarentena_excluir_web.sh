#!/bin/bash
# antivirus_quarentena_excluir_web.sh <caminho>
# So aceita caminhos dentro de /var/quarantine/rd-intranet -- nunca apaga
# nada fora dali a partir de um valor vindo do formulario.

set -u

QUARENTENA="/var/quarantine/rd-intranet"
CAMINHO="${1:-}"

CAMINHO_REAL="$(readlink -f "$CAMINHO" 2>/dev/null || true)"
if [ -z "$CAMINHO_REAL" ] || [[ "$CAMINHO_REAL" != "$QUARENTENA"* ]]; then
  echo '{"success":false,"message":"Caminho fora da quarentena."}'
  exit 1
fi

if [ ! -f "$CAMINHO_REAL" ]; then
  echo '{"success":false,"message":"Arquivo não encontrado."}'
  exit 1
fi

rm -f "$CAMINHO_REAL"
echo '{"success":true,"message":"Arquivo excluído da quarentena."}'
