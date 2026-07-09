#!/bin/bash
# iptables_ip_forward_web.sh
# Habilita o encaminhamento de pacotes IPv4 (necessario pra NAT/masquerade e
# port forward funcionarem) e persiste via /etc/sysctl.d, pra sobreviver a reboot.

set -u

echo 1 > /proc/sys/net/ipv4/ip_forward 2>/dev/null

mkdir -p /etc/sysctl.d
cat > /etc/sysctl.d/99-rd-intranet-ip-forward.conf <<'EOF'
net.ipv4.ip_forward=1
EOF

sysctl -p /etc/sysctl.d/99-rd-intranet-ip-forward.conf >/dev/null 2>&1

echo '{"success":true,"message":"Encaminhamento de pacotes (ip_forward) habilitado e persistido."}'
