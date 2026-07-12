#!/bin/bash
# apache_log_ver_web.sh <nome> [linhas]
# So leitura. /var/log/apache2 e root:adm 640 -- www-data (dono do
# processo PHP) nao consegue ler direto (o proprio apache2 escreve
# porque abre o arquivo como root antes de baixar privilegio), por isso
# precisa de sudo mesmo so pra tail.

set -u

NOME="${1:-}"
LINHAS="${2:-200}"

if ! [[ "$NOME" =~ ^[a-zA-Z0-9_.-]+\.log$ ]]; then
  echo "Nome de log inválido."
  exit 1
fi
if ! [[ "$LINHAS" =~ ^[0-9]+$ ]]; then
  LINHAS=200
fi
if [ "$LINHAS" -gt 1000 ]; then
  LINHAS=1000
fi

CAMINHO="/var/log/apache2/${NOME}"

if [ ! -f "$CAMINHO" ]; then
  echo "Arquivo não encontrado."
  exit 1
fi

tail -n "$LINHAS" "$CAMINHO"
