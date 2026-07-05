#!/bin/bash
# network_rollback_web.sh <config_atual> <backup_ou_vazio>
# Disparado automaticamente pelo systemd-run agendado em network_aplicar_web.sh
# caso a alteracao nao seja confirmada dentro do prazo.

CONFIG="$1"
BACKUP="$2"

if [ -n "$BACKUP" ] && [ -f "$BACKUP" ]; then
  cp "$BACKUP" "$CONFIG"
else
  rm -f "$CONFIG"
fi

netplan generate >/dev/null 2>&1
netplan apply >/dev/null 2>&1

logger -t rd-netplan "Rollback automatico de rede executado (alteracao nao confirmada dentro do prazo)."
