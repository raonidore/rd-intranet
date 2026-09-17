#!/bin/bash
# vlan_rollback_web.sh [<config_atual> <backup_ou_vazio>]
#
# Restaura o netplan de VLANs anterior. Disparado automaticamente pelo
# systemd-run agendado em vlan_aplicar_web.sh quando a mudanca nao e
# confirmada a tempo, ou chamado sem argumento (le o par pendente
# gravado em disco) por um botao de "reverter agora" manual antes do
# prazo acabar -- mesmo padrao de dhcp_rollback_web.sh.

CONFIG="${1:-}"
BACKUP="${2:-}"

if [ -z "$CONFIG" ] && [ -f /etc/rd-intranet/.vlan-backup-pendente ]; then
  PENDENTE="$(cat /etc/rd-intranet/.vlan-backup-pendente)"
  CONFIG="$(echo "$PENDENTE" | cut -d'|' -f1)"
  BACKUP="$(echo "$PENDENTE" | cut -d'|' -f2)"
fi

CONFIG="${CONFIG:-/etc/netplan/95-rd-intranet-vlans.yaml}"

if [ -n "$BACKUP" ] && [ -f "$BACKUP" ]; then
  cp "$BACKUP" "$CONFIG"
else
  rm -f "$CONFIG"
fi

netplan generate >/dev/null 2>&1
netplan apply >/dev/null 2>&1

systemctl stop rd-vlan-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-vlan-rollback >/dev/null 2>&1
rm -f /etc/rd-intranet/.vlan-deadline /etc/rd-intranet/.vlan-backup-pendente

logger -t rd-vlan "Rollback de configuração de VLANs executado (automático por falta de confirmação, ou manual)."

echo '{"success":true,"message":"VLANs revertidas para o estado anterior."}'
