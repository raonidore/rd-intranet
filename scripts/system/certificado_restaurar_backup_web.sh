#!/bin/bash
# certificado_restaurar_backup_web.sh <backup_crt> <backup_key>
#
# Usado pelo PHP quando a troca de certificado passa na validacao propria
# mas a ativacao (apache2ctl configtest) falha depois -- restaura o
# certificado anterior pros caminhos atuais. Sem argumentos (primeiro
# certificado da instalacao, sem backup anterior), so remove o atual.

set -u

BACKUP_CRT="${1:-}"
BACKUP_KEY="${2:-}"

if [ -n "$BACKUP_CRT" ] && [ -f "$BACKUP_CRT" ] && [ -n "$BACKUP_KEY" ] && [ -f "$BACKUP_KEY" ]; then
  cp "$BACKUP_CRT" /etc/ssl/rd-intranet/atual.crt
  cp "$BACKUP_KEY" /etc/ssl/rd-intranet/atual.key
  chmod 644 /etc/ssl/rd-intranet/atual.crt
  chmod 600 /etc/ssl/rd-intranet/atual.key
  echo '{"success":true,"message":"Certificado anterior restaurado."}'
else
  rm -f /etc/ssl/rd-intranet/atual.crt /etc/ssl/rd-intranet/atual.key
  echo '{"success":true,"message":"Nao havia certificado anterior; estado limpo."}'
fi
