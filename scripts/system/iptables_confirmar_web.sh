#!/bin/bash
# iptables_confirmar_web.sh
# Cancela o rollback automatico agendado, mantendo o ruleset aplicado, e
# persiste em /etc/iptables/rd-intranet.rules.v4 (restaurado no boot pelo
# rd-iptables-restore.service) -- so persiste depois de confirmado, nunca
# um estado ainda nao aprovado.

if systemctl is-active --quiet rd-iptables-rollback.timer; then
  systemctl stop rd-iptables-rollback.timer >/dev/null 2>&1
  systemctl reset-failed rd-iptables-rollback >/dev/null 2>&1
  rm -f /etc/rd-intranet/.iptables-deadline /etc/rd-intranet/.iptables-backup-pendente

  mkdir -p /etc/iptables
  iptables-save > /etc/iptables/rd-intranet.rules.v4 2>/dev/null

  # sshd_config ja e permanente por natureza (nao precisa de passo extra de
  # persistencia); so encerra a "janela pendente" dele junto com a do firewall.
  rm -f /etc/rd-intranet/.iptables-ssh-backup-pendente

  echo '{"success":true,"message":"Alteracao confirmada e persistida (sobrevive a reinicializacoes)."}'
else
  echo '{"success":false,"message":"Nao ha alteracao pendente de confirmacao."}'
fi
