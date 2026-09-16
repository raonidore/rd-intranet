#!/bin/bash
# dhcp_desligar_web.sh
# Para e desabilita o serviço -- a config em /etc/dhcp/dhcpd.conf continua
# intacta, só o serviço para de responder na rede. Ação de baixo risco
# (o oposto de ligar), sem necessidade de janela de confirmação/reversão.

systemctl disable --now isc-dhcp-server >/dev/null 2>&1

echo '{"success":true,"message":"Servidor DHCP desligado."}'
