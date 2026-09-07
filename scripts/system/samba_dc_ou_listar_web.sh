#!/bin/bash
# samba_dc_ou_listar_web.sh
#
# Lista as Unidades Organizacionais (OUs) do dominio -- so nivel unico
# nesta versao (sem picker de arvore aninhada, ver comentario em
# samba_dc_ou_criar_web.sh).

set -u

OUS=()
while IFS= read -r LINHA; do
  [ -z "$LINHA" ] && continue
  OUS+=("$LINHA")
done < <(samba-tool ou list 2>/dev/null | sort)

if [ "${#OUS[@]}" -eq 0 ]; then
  echo "[]"
  exit 0
fi

php -r '
    $itens = array_slice($argv, 1);
    echo json_encode($itens);
' -- "${OUS[@]}"
