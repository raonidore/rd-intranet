#!/bin/bash
# Seção JSON do smbstatus
echo "### SMBSTATUS_JSON"
smbstatus --json 2>/dev/null

echo ""
echo "### DISCO"
df -h /srv/samba 2>/dev/null || df -h /

echo ""
echo "### SMBD_PROCS"
ps aux --no-headers | grep '[s]mbd' 2>/dev/null
