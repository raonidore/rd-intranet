#!/bin/bash
# network_renovar_web.sh <interface>
#
# Forca uma renovacao de concessao DHCP so nessa interface
# (networkctl renew), sem mexer nas demais interfaces nem reescrever
# nenhum netplan -- mais cirurgico que reaplicar a config inteira via
# network_aplicar_web.sh. Uso tipico: reserva de IP nova criada no
# switch/DHCP pro MAC do servidor, sem precisar esperar o proximo ciclo
# natural de renovacao (metade do tempo de concessao) nem reiniciar o
# servidor.
#
# Diferente de network_aplicar_web.sh, nao tem como agendar um
# "rollback" de verdade aqui -- o IP resultante depende do que o
# servidor DHCP/switch devolver, fora do controle deste script (nao e
# uma reescrita de config que da pra desfazer sozinha). Por isso so
# informa o antes/depois pro admin, em vez de agendar reversao
# automatica.

set -u

IFACE="$1"

if ! ip link show "$IFACE" >/dev/null 2>&1; then
  echo '{"success":false,"message":"Interface nao encontrada."}'
  exit 1
fi

IP_ANTES=$(ip -4 -o addr show "$IFACE" | awk '{print $4}' | paste -sd ', ' -)

SAIDA=$(networkctl renew "$IFACE" 2>&1)
if [ $? -ne 0 ]; then
  ERRO=$(echo "$SAIDA" | tr '\n' ' ' | sed 's/"/\\"/g')
  echo "{\"success\":false,\"message\":\"Falha ao renovar (a interface pode nao estar em modo DHCP): ${ERRO}\"}"
  exit 1
fi

# dá um tempo pro DHCP responder antes de checar o resultado
sleep 3

IP_DEPOIS=$(ip -4 -o addr show "$IFACE" | awk '{print $4}' | paste -sd ', ' -)

MUDOU="false"
[ "$IP_ANTES" != "$IP_DEPOIS" ] && MUDOU="true"

echo "{\"success\":true,\"ip_antes\":\"${IP_ANTES}\",\"ip_depois\":\"${IP_DEPOIS}\",\"mudou\":${MUDOU}}"
