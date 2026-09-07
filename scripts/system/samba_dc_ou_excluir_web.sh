#!/bin/bash
# samba_dc_ou_excluir_web.sh <ou_dn>
#
# <ou_dn> vem exatamente como devolvido por "samba-tool ou list" (ex:
# "OU=Financeiro,DC=empresa,DC=local") -- evita reconstruir o DN na mao e
# divergir do que o dominio realmente tem.

set -u

OU_DN="$1"

if [[ ! "$OU_DN" =~ ^OU= ]]; then
  echo '{"success":false,"message":"DN de OU inválido."}'
  exit 1
fi

SAIDA=$(samba-tool ou delete "$OU_DN" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  php -r 'echo json_encode(["success" => true, "message" => "OU excluída."]);'
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao excluir OU (pode não estar vazia): " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
