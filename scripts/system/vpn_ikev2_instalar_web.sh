#!/bin/bash
# vpn_ikev2_instalar_web.sh
# Instala strongswan (+ easy-rsa, reaproveitado do OpenVPN -- mesma PKI).
# Cria a estrutura de diretorios que os demais scripts assumem que
# existe. Nao inicializa a PKI nem escreve ipsec.conf/secrets aqui --
# ficam pra botoes proprios na tela, igual ao OpenVPN.

set -u

export DEBIAN_FRONTEND=noninteractive

if ! apt-get install -y -qq strongswan easy-rsa >/tmp/rd_ikev2_out_$$ 2>/tmp/rd_ikev2_err_$$; then
  ERRO="$(tail -20 /tmp/rd_ikev2_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_ikev2_out_$$ /tmp/rd_ikev2_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar strongswan/easy-rsa: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_ikev2_out_$$ /tmp/rd_ikev2_err_$$

mkdir -p /etc/rd-intranet/ikev2/tmp/cacerts
chown -R root:www-data /etc/rd-intranet/ikev2/tmp
chmod 775 /etc/rd-intranet/ikev2/tmp /etc/rd-intranet/ikev2/tmp/cacerts

mkdir -p /etc/rd-intranet/ikev2/backups
chmod 750 /etc/rd-intranet/ikev2/backups

mkdir -p /etc/rd-intranet/ikev2/cacerts
chmod 755 /etc/rd-intranet/ikev2/cacerts

echo 1 > /proc/sys/net/ipv4/ip_forward 2>/dev/null
mkdir -p /etc/sysctl.d
cat > /etc/sysctl.d/99-rd-intranet-ip-forward.conf <<'EOF'
net.ipv4.ip_forward=1
EOF
sysctl -p /etc/sysctl.d/99-rd-intranet-ip-forward.conf >/dev/null 2>&1

echo '{"success":true,"message":"strongSwan instalado. Inicialize a PKI e configure o servidor em seguida."}'
