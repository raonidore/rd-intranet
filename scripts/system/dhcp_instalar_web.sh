#!/bin/bash
# dhcp_instalar_web.sh
#
# Instala o isc-dhcp-server (ainda disponivel no repositorio "universe"
# do Ubuntu 24.04 mesmo apos o fim de vida upstream do projeto ISC --
# confirmado ao vivo via apt-cache antes de escolher este pacote em vez
# de kea-dhcp4-server/dnsmasq: formato de config mais simples/conhecido,
# e tem um "modo teste" real (dhcpd -t -cf) que valida sintaxe sem
# precisar subir o servico -- mesmo espirito de testparm -s do Samba e
# iptables-restore --test do Firewall, ja usados neste projeto).
#
# CRITICO: o pacote tenta iniciar o servico sozinho no post-install, e
# SEM nenhuma config real ainda (interface nao definida em
# /etc/default/isc-dhcp-server), isso falha ou fica num estado
# indefinido. Para/desabilita explicitamente logo em seguida -- o
# servico so deve subir de novo quando o admin aplicar uma config de
# verdade pela tela (dhcp_aplicar_web.sh) e/ou clicar em "Ligar".

set -u
export DEBIAN_FRONTEND=noninteractive

if ! command -v dhcpd >/dev/null 2>&1; then
  if ! apt-get install -y -qq isc-dhcp-server >/tmp/rd_dhcp_out_$$ 2>/tmp/rd_dhcp_err_$$; then
    ERRO="$(tail -20 /tmp/rd_dhcp_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_dhcp_out_$$ /tmp/rd_dhcp_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar isc-dhcp-server: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_dhcp_out_$$ /tmp/rd_dhcp_err_$$
fi

systemctl stop isc-dhcp-server >/dev/null 2>&1
systemctl disable isc-dhcp-server >/dev/null 2>&1

echo '{"success":true,"message":"isc-dhcp-server instalado (serviço parado -- configure e aplique antes de ligar)."}'
