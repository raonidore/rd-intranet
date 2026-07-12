#!/bin/bash
# vpn_wireguard_instalar_web.sh
# Instala wireguard-tools (o modulo de kernel ja vem embutido nos
# kernels modernos, sem DKMS) + qrencode (pros peers baixarem via QR
# code). Cria a estrutura de diretorios que vpn_wireguard_aplicar_web.sh
# assume que existe (mesmo padrao do Samba: dir tmp root:www-data 775
# onde o PHP escreve o wg0.conf gerado antes do script root aplicar).
# Nao gera chave de servidor nem sobe interface -- isso fica por conta
# do primeiro "salvar config" feito pela tela (VpnWireguardService).

set -u

export DEBIAN_FRONTEND=noninteractive

if ! apt-get install -y -qq wireguard-tools qrencode >/tmp/rd_vpnwg_out_$$ 2>/tmp/rd_vpnwg_err_$$; then
  ERRO="$(tail -20 /tmp/rd_vpnwg_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_vpnwg_out_$$ /tmp/rd_vpnwg_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar wireguard-tools/qrencode: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_vpnwg_out_$$ /tmp/rd_vpnwg_err_$$

mkdir -p /etc/wireguard/rd/tmp
chown root:www-data /etc/wireguard/rd/tmp
chmod 775 /etc/wireguard/rd/tmp

mkdir -p /etc/wireguard/rd/backups
chmod 750 /etc/wireguard/rd/backups

echo 1 > /proc/sys/net/ipv4/ip_forward 2>/dev/null
mkdir -p /etc/sysctl.d
cat > /etc/sysctl.d/99-rd-intranet-ip-forward.conf <<'EOF'
net.ipv4.ip_forward=1
EOF
sysctl -p /etc/sysctl.d/99-rd-intranet-ip-forward.conf >/dev/null 2>&1

echo '{"success":true,"message":"WireGuard instalado. Configure o servidor para gerar as chaves e subir a interface."}'
