#!/bin/bash
# antivirus_status_web.sh
# Somente leitura: status do ClamAV e se o modo tempo-real (vfs_virusfilter
# no Samba) esta ativo.

set -u

INSTALADO=0
command -v clamdscan >/dev/null 2>&1 && INSTALADO=1

CLAMD_ATIVO=0
systemctl is-active --quiet clamav-daemon 2>/dev/null && CLAMD_ATIVO=1

FRESHCLAM_ATIVO=0
systemctl is-active --quiet clamav-freshclam 2>/dev/null && FRESHCLAM_ATIVO=1

VERSAO=""
if [ "$INSTALADO" = "1" ]; then
  VERSAO="$(clamdscan --version 2>/dev/null | head -1)"
fi

TEMPO_REAL=0
if [ -s /etc/samba/antivirus.conf ]; then
  TEMPO_REAL=1
fi

echo "instalado|${INSTALADO}"
echo "clamd_ativo|${CLAMD_ATIVO}"
echo "freshclam_ativo|${FRESHCLAM_ATIVO}"
echo "versao|${VERSAO}"
echo "tempo_real_ativo|${TEMPO_REAL}"
