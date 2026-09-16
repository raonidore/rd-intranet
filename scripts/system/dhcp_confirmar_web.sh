#!/bin/bash
# dhcp_confirmar_web.sh
# Cancela o rollback automatico agendado, mantendo a config aplicada --
# mesmo espirito de iptables_confirmar_web.sh. dhcpd.conf ja e o proprio
# arquivo que o systemd le no boot, entao nao precisa de passo extra de
# persistencia (diferente do iptables, que precisa salvar num arquivo
# separado restaurado por um service proprio no boot).

if systemctl is-active --quiet rd-dhcp-rollback.timer; then
  systemctl stop rd-dhcp-rollback.timer >/dev/null 2>&1
  systemctl reset-failed rd-dhcp-rollback >/dev/null 2>&1
  rm -f /etc/rd-intranet/.dhcp-deadline /etc/rd-intranet/.dhcp-backup-pendente
  echo '{"success":true,"message":"Alteração confirmada -- configuração permanente."}'
else
  echo '{"success":false,"message":"Não há alteração pendente de confirmação."}'
fi
