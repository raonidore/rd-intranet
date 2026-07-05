#!/bin/bash
# apply_apache_config_web.sh <tmpfile>
#
# Aplica as diretivas gerenciadas num arquivo proprio da RD Intranet
# (conf-available/rd-intranet.conf, habilitado via a2enconf) -- nunca
# toca no apache2.conf da distro. Como nao da pra validar um snippet
# isolado com apache2ctl configtest, o padrao aqui e: troca primeiro,
# testa depois, desfaz se ficar invalido (mesmo padrao de
# apply_shares_conf_web.sh).

TEMP_FILE="$1"

if [ ! -f "$TEMP_FILE" ]; then
  echo "Arquivo temporário não encontrado"
  exit 1
fi

if ! apache2ctl configtest >/dev/null 2>&1; then
  echo "Configuração atual já está inválida, abortando"
  apache2ctl configtest 2>&1
  exit 1
fi

ARQUIVO_RD="/etc/apache2/conf-available/rd-intranet.conf"
BACKUP_DIR="/etc/apache2/rd/backups"
mkdir -p "$BACKUP_DIR"
BACKUP="${BACKUP_DIR}/rd-intranet_$(date +%Y%m%d%H%M%S).conf"

if [ -f "$ARQUIVO_RD" ]; then
  cp "$ARQUIVO_RD" "$BACKUP"
  chmod 644 "$BACKUP"
fi

cp "$TEMP_FILE" "$ARQUIVO_RD"
chown root:root "$ARQUIVO_RD"
chmod 644 "$ARQUIVO_RD"
a2enconf rd-intranet >/dev/null 2>&1

if ! apache2ctl configtest >/dev/null 2>&1; then
  echo "Nova configuração inválida. Restaurando backup..."
  if [ -f "$BACKUP" ]; then
    cp "$BACKUP" "$ARQUIVO_RD"
  else
    rm -f "$ARQUIVO_RD"
    a2disconf rd-intranet >/dev/null 2>&1
  fi
  systemctl reload apache2
  apache2ctl configtest 2>&1
  exit 1
fi

systemctl reload apache2

echo "OK"
echo "Backup criado em: $BACKUP"
