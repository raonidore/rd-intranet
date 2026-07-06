#!/bin/bash
# network_confirmar_web.sh
# Cancela o rollback automatico agendado, mantendo a configuracao aplicada definitivamente.

if systemctl is-active --quiet rd-netplan-rollback.timer; then
  systemctl stop rd-netplan-rollback.timer >/dev/null 2>&1
  systemctl reset-failed rd-netplan-rollback >/dev/null 2>&1
  rm -f /etc/rd-intranet/.rede-deadline
  echo '{"success":true,"message":"Alteracao confirmada e mantida definitivamente."}'
else
  echo '{"success":false,"message":"Nao ha alteracao pendente de confirmacao."}'
fi
