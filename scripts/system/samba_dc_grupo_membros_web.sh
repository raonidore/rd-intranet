#!/bin/bash
# samba_dc_grupo_membros_web.sh <nome>

set -u

NOME="$1"

MEMBROS=()
while IFS= read -r M; do
  [ -z "$M" ] && continue
  MEMBROS+=("$M")
done < <(samba-tool group listmembers "$NOME" 2>/dev/null | sort)

if [ "${#MEMBROS[@]}" -eq 0 ]; then
  echo "[]"
  exit 0
fi

php -r '
    $itens = array_slice($argv, 1);
    echo json_encode($itens);
' -- "${MEMBROS[@]}"
