#!/bin/bash
# vlan_confirmar_web.sh
# Cancela o rollback automatico agendado, mantendo as VLANs aplicadas definitivamente.

if systemctl is-active --quiet rd-vlan-rollback.timer; then
  systemctl stop rd-vlan-rollback.timer >/dev/null 2>&1
  systemctl reset-failed rd-vlan-rollback >/dev/null 2>&1
  rm -f /etc/rd-intranet/.vlan-deadline
  echo '{"success":true,"message":"Alteracao de VLANs confirmada e mantida definitivamente."}'
else
  echo '{"success":false,"message":"Nao ha alteracao de VLANs pendente de confirmacao."}'
fi
