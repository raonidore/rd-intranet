#!/bin/bash
# vpn_openvpn_instalar_web.sh
# Instala openvpn + easy-rsa. Cria a estrutura de diretorios que os
# demais scripts (aplicar config, pki) assumem que existe -- mesmo
# padrao do WireGuard/Samba (dir tmp root:www-data 775 onde o PHP
# escreve a config gerada antes do script root aplicar). NAO inicializa
# a PKI aqui -- fica pra um botao proprio na tela (pode demorar alguns
# segundos e o admin deve ver claramente que so acontece uma vez).

set -u

export DEBIAN_FRONTEND=noninteractive

if ! apt-get install -y -qq openvpn easy-rsa >/tmp/rd_ovpn_out_$$ 2>/tmp/rd_ovpn_err_$$; then
  ERRO="$(tail -20 /tmp/rd_ovpn_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_ovpn_out_$$ /tmp/rd_ovpn_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar openvpn/easy-rsa: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_ovpn_out_$$ /tmp/rd_ovpn_err_$$

mkdir -p /etc/openvpn/server/rd/tmp
chown root:www-data /etc/openvpn/server/rd/tmp
chmod 775 /etc/openvpn/server/rd/tmp

mkdir -p /etc/openvpn/server/rd/backups
chmod 750 /etc/openvpn/server/rd/backups

mkdir -p /etc/openvpn/client

echo 1 > /proc/sys/net/ipv4/ip_forward 2>/dev/null
mkdir -p /etc/sysctl.d
cat > /etc/sysctl.d/99-rd-intranet-ip-forward.conf <<'EOF'
net.ipv4.ip_forward=1
EOF
sysctl -p /etc/sysctl.d/99-rd-intranet-ip-forward.conf >/dev/null 2>&1

echo '{"success":true,"message":"OpenVPN instalado. Inicialize a PKI e configure o servidor em seguida."}'
