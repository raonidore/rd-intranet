#!/bin/bash
# samba_dc_grupo_listar_web.sh
#
# Lista grupos do dominio com contagem de membros.

set -u

LINHAS=()

while IFS= read -r NOME; do
  [ -z "$NOME" ] && continue

  QTD=$(samba-tool group listmembers "$NOME" 2>/dev/null | grep -c .)

  LINHAS+=("${NOME}|${QTD}")
done < <(samba-tool group list 2>/dev/null | sort)

if [ "${#LINHAS[@]}" -eq 0 ]; then
  echo "[]"
  exit 0
fi

php -r '
    $itens = [];
    for ($i = 1; $i < count($argv); $i++) {
        [$nome, $membros] = explode("|", $argv[$i], 2);
        $itens[] = ["nome" => $nome, "membros" => (int)$membros];
    }
    echo json_encode($itens);
' -- "${LINHAS[@]}"
