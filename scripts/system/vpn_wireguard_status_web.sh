#!/bin/bash
# vpn_wireguard_status_web.sh <interface>
# So leitura -- nunca muda nada. Roda como root por consistencia com o
# resto do app (nao chama nada privilegiado de fato, "wg show" funciona
# sem root pra qualquer interface ja existente).
#
# Saida:
#   IFACE_UP|0 ou 1
#   PEER|<chave_publica>|<endpoint>|<ultimo_handshake_unix>|<rx_bytes>|<tx_bytes>
#   (uma linha PEER por peer configurado na interface)

set -u

IFACE="${1:-wg0}"

if ! [[ "$IFACE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo "ERRO|Nome de interface inválido."
  exit 1
fi

if ! ip link show "$IFACE" >/dev/null 2>&1; then
  echo "IFACE_UP|0"
  exit 0
fi

echo "IFACE_UP|1"

wg show "$IFACE" dump 2>/dev/null | tail -n +2 | while IFS=$'\t' read -r PUBKEY PRESHARED ENDPOINT ALLOWEDIPS HANDSHAKE RX TX KEEPALIVE; do
  echo "PEER|${PUBKEY}|${ENDPOINT}|${HANDSHAKE}|${RX}|${TX}"
done
