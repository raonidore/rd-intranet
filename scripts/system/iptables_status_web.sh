#!/bin/bash
# iptables_status_web.sh
# Somente leitura: estado atual do firewall pra tela de Infraestrutura > Firewall.

echo "=== IPTABLES-SAVE ==="
iptables-save 2>&1

echo "=== UFW-STATUS ==="
if command -v ufw >/dev/null 2>&1; then
  ufw status verbose 2>&1
else
  echo "ufw nao instalado"
fi

echo "=== IP-FORWARD ==="
cat /proc/sys/net/ipv4/ip_forward 2>/dev/null || echo "0"

echo "=== SSH-PORT ==="
grep -iE '^\s*Port\s+[0-9]+' /etc/ssh/sshd_config 2>/dev/null | awk '{print $2}' | tail -1

echo "=== ROLLBACK-PENDENTE ==="
if systemctl is-active --quiet rd-iptables-rollback.timer 2>/dev/null; then
  echo "1"
  cat /etc/rd-intranet/.iptables-deadline 2>/dev/null || echo "0"
else
  echo "0"
  echo "0"
fi
