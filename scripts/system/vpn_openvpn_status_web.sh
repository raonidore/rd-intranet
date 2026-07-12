#!/bin/bash
# vpn_openvpn_status_web.sh
# So leitura. Espera que o server.conf gerado pela tela tenha
# "status-version 3" (formato previsivel, tab-separated) apontando pra
# /etc/openvpn/server/openvpn-status.log.
#
# Saida:
#   SERVIDOR_ATIVO|0 ou 1
#   CLIENTE|<nome>|<endereco_real>|<rx_bytes>|<tx_bytes>|<conectado_desde_unix>

set -u

UNIDADE="openvpn-server@server"
STATUS_LOG="/etc/openvpn/server/openvpn-status.log"

if ! systemctl is-active --quiet "$UNIDADE"; then
  echo "SERVIDOR_ATIVO|0"
  exit 0
fi

echo "SERVIDOR_ATIVO|1"

if [ -f "$STATUS_LOG" ]; then
  awk -F'\t' '$1=="CLIENT_LIST" {print "CLIENTE|" $2 "|" $3 "|" $6 "|" $7 "|" $9}' "$STATUS_LOG"
fi
