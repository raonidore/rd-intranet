#!/bin/bash
# samba_dc_computador_listar_web.sh
#
# Lista computadores ingressados no dominio -- somente leitura nesta
# versao, sem nenhuma acao (fora do escopo desta entrega).

set -u

COMPUTADORES=()
while IFS= read -r C; do
  [ -z "$C" ] && continue
  COMPUTADORES+=("$C")
done < <(samba-tool computer list 2>/dev/null | sort)

if [ "${#COMPUTADORES[@]}" -eq 0 ]; then
  echo "[]"
  exit 0
fi

php -r '
    $itens = array_slice($argv, 1);
    echo json_encode($itens);
' -- "${COMPUTADORES[@]}"
