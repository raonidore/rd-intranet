#!/bin/bash
BACKUP="$1"
ARQUIVO_RD="/etc/apache2/conf-available/rd-intranet.conf"

if [ -z "$BACKUP" ] || echo "$BACKUP" | grep -q '\.\.'; then
    echo '{"success":false,"message":"Arquivo invalido"}'; exit 1
fi
if [ ! -f "$BACKUP" ] || [[ "$BACKUP" != /etc/apache2/rd/backups/rd-intranet_* ]]; then
    echo '{"success":false,"message":"Backup nao encontrado"}'; exit 1
fi

NOVO_BKP="/etc/apache2/rd/backups/rd-intranet_$(date +%Y%m%d%H%M%S)_antes_restore.conf"
[ -f "$ARQUIVO_RD" ] && cp "$ARQUIVO_RD" "$NOVO_BKP" 2>/dev/null

cp "$BACKUP" "$ARQUIVO_RD" 2>/dev/null || { echo '{"success":false,"message":"Falha ao restaurar"}'; exit 1; }
chown root:root "$ARQUIVO_RD"
chmod 644 "$ARQUIVO_RD"
a2enconf rd-intranet >/dev/null 2>&1

if ! apache2ctl configtest >/dev/null 2>&1; then
  echo '{"success":false,"message":"Backup restaurado, mas config ficou invalida. Restaure outro backup ou corrija manualmente."}'
  exit 1
fi

systemctl reload apache2 2>/dev/null
echo '{"success":true,"message":"Backup restaurado com sucesso"}'
