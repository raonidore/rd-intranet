#!/bin/bash
BACKUP="$1"
if [ -z "$BACKUP" ] || echo "$BACKUP" | grep -q '\.\.'; then
    echo '{"success":false,"message":"Arquivo invalido"}'; exit 1
fi
if [ ! -f "$BACKUP" ] || [[ "$BACKUP" != /etc/samba/smb.conf.bkp.* ]]; then
    echo '{"success":false,"message":"Backup nao encontrado"}'; exit 1
fi
NOVO_BKP="/etc/samba/smb.conf.bkp.$(date +%Y%m%d%H%M%S)_antes_restore"
cp /etc/samba/smb.conf "$NOVO_BKP" 2>/dev/null
cp "$BACKUP" /etc/samba/smb.conf 2>/dev/null || (echo '{"success":false,"message":"Falha ao restaurar"}'; exit 1)
systemctl reload smbd 2>/dev/null
echo '{"success":true,"message":"Backup restaurado com sucesso"}'
