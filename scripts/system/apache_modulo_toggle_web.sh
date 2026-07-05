#!/bin/bash
# apache_modulo_toggle_web.sh <nome> <enable|disable>
#
# Habilita/desabilita um modulo Apache (a2enmod/a2dismod), valida com
# apache2ctl configtest antes de recarregar, e desfaz se a config ficar
# invalida -- mesmo padrao de backup+validacao ja usado no Samba, so que
# aqui em vez de arquivo trocado, o "desfazer" e reverter o toggle.
#
# Protege modulos essenciais pro Apache continuar de pe e pra RD Intranet
# continuar funcionando (ela roda como mod_php, depende de mod_rewrite
# pro roteamento via .htaccess, e mpm_prefork e o unico MPM habilitado).

NOME="$1"
ACAO="$2"

PROTEGIDOS="mpm_prefork php8.3 rewrite"

if [[ ! "$NOME" =~ ^[a-z0-9_.-]+$ ]]; then
  echo "Nome de modulo invalido"
  exit 1
fi

for p in $PROTEGIDOS; do
  if [ "$NOME" = "$p" ]; then
    echo "Modulo '$NOME' e essencial e nao pode ser desabilitado por aqui"
    exit 1
  fi
done

if [ ! -f "/etc/apache2/mods-available/${NOME}.load" ]; then
  echo "Modulo '$NOME' nao existe"
  exit 1
fi

case "$ACAO" in
  enable)
    a2enmod "$NOME" >/dev/null
    ;;
  disable)
    a2dismod "$NOME" >/dev/null
    ;;
  *)
    echo "Acao invalida"
    exit 1
    ;;
esac

if ! apache2ctl configtest >/dev/null 2>&1; then
  echo "Configuracao ficou invalida, desfazendo..."
  case "$ACAO" in
    enable) a2dismod "$NOME" >/dev/null ;;
    disable) a2enmod "$NOME" >/dev/null ;;
  esac
  apache2ctl configtest 2>&1
  exit 1
fi

systemctl reload apache2

echo "OK"
