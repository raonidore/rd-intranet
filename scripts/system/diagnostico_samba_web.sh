#!/bin/bash

echo "### SERVICOS"
echo "SMBD_STATUS=$(systemctl is-active smbd)"
echo "NMBD_STATUS=$(systemctl is-active nmbd 2>/dev/null || echo unknown)"
echo "SMBD_ENABLED=$(systemctl is-enabled smbd 2>/dev/null || echo unknown)"
echo "NMBD_ENABLED=$(systemctl is-enabled nmbd 2>/dev/null || echo unknown)"

echo ""
echo "### TESTPARM"
testparm -s /etc/samba/smb.conf >/tmp/rd_testparm_diag.out 2>&1
if [ $? -eq 0 ]; then
  echo "TESTPARM_STATUS=OK"
else
  echo "TESTPARM_STATUS=ERRO"
fi
cat /tmp/rd_testparm_diag.out

echo ""
echo "### DISCO"
df -h /srv/samba 2>/dev/null || df -h /

echo ""
echo "### PASTAS"
find /srv/samba/Compartilhamentos -maxdepth 1 -mindepth 1 -type d | while read dir; do
  nome=$(basename "$dir")
  owner=$(stat -c '%U' "$dir" 2>/dev/null)
  grupo=$(stat -c '%G' "$dir" 2>/dev/null)
  modo=$(stat -c '%a' "$dir" 2>/dev/null)
  tamanho=$(du -sh "$dir" 2>/dev/null | cut -f1)
  echo "$nome|$dir|$owner|$grupo|$modo|$tamanho"
done

echo ""
echo "### GRUPOS"
find /srv/samba/Compartilhamentos -maxdepth 1 -mindepth 1 -type d | while read dir; do
  grupo=$(stat -c '%G' "$dir" 2>/dev/null)
  getent group "$grupo"
done | sort -u

echo ""
echo "### LOGS_RECENTES"
journalctl -u smbd -n 80 --no-pager 2>/dev/null | tail -80

echo ""
echo "### SMBSTATUS"
smbstatus 2>/dev/null
