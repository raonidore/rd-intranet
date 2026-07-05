#!/bin/bash

TEMP_FILE="$1"

if [ ! -f "$TEMP_FILE" ]; then
  echo "Arquivo temporário não encontrado"
  exit 1
fi

testparm -s "$TEMP_FILE" >/dev/null 2>&1

if [ $? -ne 0 ]; then
  echo "Configuração inválida"
  testparm -s "$TEMP_FILE" 2>&1
  exit 1
fi

BACKUP="/etc/samba/smb.conf.bkp.$(date +%Y%m%d%H%M%S)"

cp /etc/samba/smb.conf "$BACKUP"
cp "$TEMP_FILE" /etc/samba/smb.conf

systemctl reload smbd

echo "OK"
echo "Backup criado em: $BACKUP"
