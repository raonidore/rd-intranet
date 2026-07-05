#!/bin/bash

TEMP_FILE="$1"

if [ ! -f "$TEMP_FILE" ]; then
  echo "Arquivo temporário não encontrado"
  exit 1
fi

testparm -s /etc/samba/smb.conf >/dev/null 2>&1

if [ $? -ne 0 ]; then
  echo "smb.conf atual inválido"
  testparm -s /etc/samba/smb.conf 2>&1
  exit 1
fi

BACKUP="/etc/samba/rd/backups/shares_$(date +%Y%m%d%H%M%S).conf"

cp /etc/samba/shares.conf "$BACKUP"
cp "$TEMP_FILE" /etc/samba/shares.conf

testparm -s /etc/samba/smb.conf >/dev/null 2>&1

if [ $? -ne 0 ]; then
  echo "Nova configuração inválida. Restaurando backup..."
  cp "$BACKUP" /etc/samba/shares.conf
  systemctl reload smbd
  testparm -s /etc/samba/smb.conf 2>&1
  exit 1
fi

systemctl reload smbd

echo "OK"
echo "Backup criado em: $BACKUP"
