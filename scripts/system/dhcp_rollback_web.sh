#!/bin/bash
# dhcp_rollback_web.sh ["<backup_conf>|<backup_defaults>|<estava_ativo>"]
#
# Restaura a config anterior do DHCP. Disparado automaticamente pelo
# systemd-run agendado em dhcp_aplicar_web.sh quando a mudanca nao e
# confirmada a tempo, ou chamado direto (sem argumento, le o arquivo
# pendente) por um botao de "reverter agora" manual antes do prazo
# acabar -- mesmo padrao de iptables_rollback_web.sh.

ARG="${1:-}"

if [ -z "$ARG" ] && [ -f /etc/rd-intranet/.dhcp-backup-pendente ]; then
  ARG="$(cat /etc/rd-intranet/.dhcp-backup-pendente)"
fi

CONF="/etc/dhcp/dhcpd.conf"
DEFAULTS="/etc/default/isc-dhcp-server"

if [ -n "$ARG" ]; then
  BACKUP_CONF="$(echo "$ARG" | cut -d'|' -f1)"
  BACKUP_DEFAULTS="$(echo "$ARG" | cut -d'|' -f2)"
  ESTAVA_ATIVO="$(echo "$ARG" | cut -d'|' -f3)"

  if [ -f "$BACKUP_CONF" ]; then
    cp "$BACKUP_CONF" "$CONF"
  else
    rm -f "$CONF"
  fi

  if [ -f "$BACKUP_DEFAULTS" ]; then
    cp "$BACKUP_DEFAULTS" "$DEFAULTS"
  else
    rm -f "$DEFAULTS"
  fi

  if [ "$ESTAVA_ATIVO" = "1" ]; then
    systemctl restart isc-dhcp-server >/dev/null 2>&1
  else
    systemctl stop isc-dhcp-server >/dev/null 2>&1
    # limpa o estado "failed" que a config ruim deixou -- ja foi
    # corrigido, nao deve continuar aparecendo como problema na tela
    systemctl reset-failed isc-dhcp-server >/dev/null 2>&1
  fi
fi

systemctl stop rd-dhcp-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-dhcp-rollback >/dev/null 2>&1
rm -f /etc/rd-intranet/.dhcp-deadline /etc/rd-intranet/.dhcp-backup-pendente

logger -t rd-dhcp "Rollback de configuração DHCP executado (automático por falta de confirmação, ou manual)."

echo '{"success":true,"message":"DHCP revertido para o estado anterior."}'
