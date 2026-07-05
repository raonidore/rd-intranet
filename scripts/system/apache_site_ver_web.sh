#!/bin/bash
# apache_site_ver_web.sh <nome_arquivo.conf> — mostra o conteudo de um vhost.

NOME="$1"

if [[ ! "$NOME" =~ ^[A-Za-z0-9_.-]+\.conf$ ]]; then
  echo "Nome de arquivo invalido"
  exit 1
fi

ARQUIVO="/etc/apache2/sites-available/${NOME}"

if [ ! -f "$ARQUIVO" ]; then
  echo "Site nao encontrado"
  exit 1
fi

cat "$ARQUIVO"
