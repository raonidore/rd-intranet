#!/bin/bash
# apache_site_toggle_web.sh <nome_arquivo.conf> <enable|disable>
#
# Habilita/desabilita um site (a2ensite/a2dissite), valida com
# apache2ctl configtest antes de recarregar, desfaz se ficar invalido --
# mesmo padrao do apache_modulo_toggle_web.sh.

NOME="$1"
ACAO="$2"

if [[ ! "$NOME" =~ ^[A-Za-z0-9_.-]+\.conf$ ]]; then
  echo "Nome de arquivo invalido"
  exit 1
fi

if [ ! -f "/etc/apache2/sites-available/${NOME}" ]; then
  echo "Site nao encontrado"
  exit 1
fi

case "$ACAO" in
  enable)
    a2ensite "$NOME" >/dev/null
    ;;
  disable)
    a2dissite "$NOME" >/dev/null
    ;;
  *)
    echo "Acao invalida"
    exit 1
    ;;
esac

if ! apache2ctl configtest >/dev/null 2>&1; then
  echo "Configuracao ficou invalida, desfazendo..."
  case "$ACAO" in
    enable) a2dissite "$NOME" >/dev/null ;;
    disable) a2ensite "$NOME" >/dev/null ;;
  esac
  apache2ctl configtest 2>&1
  exit 1
fi

systemctl reload apache2

echo "OK"
