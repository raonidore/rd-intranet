#!/bin/bash
# network_aplicar_web.sh <interface> <modo:dhcp|estatico> <ip_cidr|-> <gateway|-> <dns_csv|->
#
# Grava um netplan de override (90-rd-intranet.yaml, prioridade maior que o
# 50-cloud-init.yaml) e aplica. Agenda uma reversao automatica em 120s via
# systemd-run, cancelavel por network_confirmar_web.sh — se a mudanca
# derrubar o acesso, o servidor se autocorrige sozinho.

set -u

IFACE="$1"
MODO="$2"
IP_CIDR="$3"
GATEWAY="$4"
DNS_CSV="$5"

CONFIG="/etc/netplan/90-rd-intranet.yaml"
BACKUP_DIR="/etc/netplan/.rd-backups"
mkdir -p "$BACKUP_DIR"

if ! ip link show "$IFACE" >/dev/null 2>&1; then
  echo '{"success":false,"message":"Interface nao encontrada."}'
  exit 1
fi

BACKUP=""
if [ -f "$CONFIG" ]; then
  BACKUP="$BACKUP_DIR/90-rd-intranet.yaml.bkp.$(date +%Y%m%d%H%M%S)"
  cp "$CONFIG" "$BACKUP"
fi

if [ "$MODO" = "dhcp" ]; then
  cat > "$CONFIG" <<EOF
network:
  version: 2
  ethernets:
    $IFACE:
      dhcp4: true
EOF
else
  IFS=',' read -ra DNS_ARR <<< "$DNS_CSV"
  DNS_YAML=""
  for d in "${DNS_ARR[@]}"; do
    d="$(echo "$d" | xargs)"
    [ -z "$d" ] && continue
    DNS_YAML="${DNS_YAML}${DNS_YAML:+, }$d"
  done

  cat > "$CONFIG" <<EOF
network:
  version: 2
  ethernets:
    $IFACE:
      dhcp4: false
      addresses: [$IP_CIDR]
      routes:
        - to: default
          via: $GATEWAY
      nameservers:
        addresses: [$DNS_YAML]
EOF
fi

chmod 600 "$CONFIG"

ERR_FILE="/tmp/rd_netplan_err_$$"
if ! netplan generate 2>"$ERR_FILE"; then
  ERRO="$(cat "$ERR_FILE" | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f "$ERR_FILE"
  if [ -n "$BACKUP" ]; then cp "$BACKUP" "$CONFIG"; else rm -f "$CONFIG"; fi
  netplan generate >/dev/null 2>&1
  echo "{\"success\":false,\"message\":\"Configuracao invalida: ${ERRO}\"}"
  exit 1
fi
rm -f "$ERR_FILE"

netplan apply

# cancela qualquer rollback pendente anterior antes de agendar um novo
systemctl stop rd-netplan-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-netplan-rollback >/dev/null 2>&1

systemd-run --unit=rd-netplan-rollback --on-active=120 \
  /opt/rdtecnologia/scripts/network_rollback_web.sh "$CONFIG" "$BACKUP" >/dev/null 2>&1

echo '{"success":true,"message":"Configuracao aplicada. Revertendo automaticamente em 120s se nao for confirmada."}'
