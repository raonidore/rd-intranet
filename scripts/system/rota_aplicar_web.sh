#!/bin/bash
# rota_aplicar_web.sh <destino_cidr> <via> <dev>
#
# Aplica uma rota extra ao vivo (ip route replace) e agenda reversao
# automatica em 120s via systemd-run, cancelavel por rota_confirmar_web.sh
# -- mesmo padrao ja usado pra interface de rede, mas com unit name proprio
# (rd-rota-rollback, nao rd-netplan-rollback) e sem tocar no netplan.

set -u

DESTINO="$1"
VIA="$2"
DEV="$3"

if ! ip link show "$DEV" >/dev/null 2>&1; then
  echo '{"success":false,"message":"Interface nao encontrada."}'
  exit 1
fi

if ! ip route replace "$DESTINO" via "$VIA" dev "$DEV" 2>/tmp/rd_rota_err_$$; then
  ERRO="$(cat /tmp/rd_rota_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_rota_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao aplicar rota: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_rota_err_$$

systemctl stop rd-rota-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-rota-rollback >/dev/null 2>&1

mkdir -p /etc/rd-intranet
echo "$DESTINO via $VIA dev $DEV" > /etc/rd-intranet/.rota-pendente
echo "$(($(date +%s) + 120))" > /etc/rd-intranet/.rota-deadline

systemd-run --unit=rd-rota-rollback --on-active=120 \
  /opt/rdtecnologia/scripts/rota_rollback_web.sh "$DESTINO" >/dev/null 2>&1

echo '{"success":true,"message":"Rota aplicada. Revertendo automaticamente em 120s se nao for confirmada."}'
