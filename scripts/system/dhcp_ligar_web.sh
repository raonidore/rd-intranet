#!/bin/bash
# dhcp_ligar_web.sh
# Liga um servico ja configurado e confirmado -- nao mexe em config
# nenhuma (isso e trabalho do dhcp_aplicar_web.sh, que ja reinicia o
# servico como parte de aplicar). Erra de proposito se nao existir
# config ainda, pra nao subir o dhcpd com o dhcpd.conf padrao vazio do
# pacote (que serve a rede toda sem faixa nenhuma definida -- inofensivo
# na pratica, mas confuso).

set -u

if [ ! -s /etc/dhcp/dhcpd.conf ] || [ ! -f /etc/default/isc-dhcp-server ]; then
  echo '{"success":false,"message":"Nenhuma configuração aplicada ainda -- configure e clique em \"Aplicar\" antes de ligar."}'
  exit 1
fi

if systemctl enable --now isc-dhcp-server >/tmp/rd_dhcp_err_$$ 2>&1; then
  rm -f /tmp/rd_dhcp_err_$$
  echo '{"success":true,"message":"Servidor DHCP ligado."}'
else
  ERRO="$(journalctl -u isc-dhcp-server -n 15 --no-pager 2>/dev/null | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_dhcp_err_$$
  echo "{\"success\":false,\"message\":\"Falha ao ligar: ${ERRO}\"}"
  exit 1
fi
