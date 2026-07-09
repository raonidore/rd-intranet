#!/bin/bash
# iptables_rollback_web.sh [arquivo_backup]
#
# Restaura o ruleset anterior. Disparado automaticamente pelo systemd-run
# agendado em iptables_aplicar_web.sh quando a mudanca nao e confirmada a
# tempo, ou chamado direto (sem argumento, le o arquivo pendente) por um
# botao de "reverter agora" manual antes do prazo acabar.

BACKUP="${1:-}"

if [ -z "$BACKUP" ] && [ -f /etc/rd-intranet/.iptables-backup-pendente ]; then
  BACKUP="$(cat /etc/rd-intranet/.iptables-backup-pendente)"
fi

if [ -n "$BACKUP" ] && [ -f "$BACKUP" ]; then
  iptables-restore < "$BACKUP" 2>/dev/null
fi

if [ -f /etc/rd-intranet/.iptables-ssh-backup-pendente ]; then
  SSH_BACKUP="$(cat /etc/rd-intranet/.iptables-ssh-backup-pendente)"
  if [ -n "$SSH_BACKUP" ] && [ -f "$SSH_BACKUP" ]; then
    cp "$SSH_BACKUP" /etc/ssh/sshd_config
    systemctl reload ssh 2>/dev/null || systemctl reload sshd 2>/dev/null
  fi
  rm -f /etc/rd-intranet/.iptables-ssh-backup-pendente
fi

systemctl stop rd-iptables-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-iptables-rollback >/dev/null 2>&1

rm -f /etc/rd-intranet/.iptables-deadline /etc/rd-intranet/.iptables-backup-pendente

logger -t rd-iptables "Rollback de firewall (e sshd, se aplicavel) executado (automatico por falta de confirmacao, ou manual)."

echo '{"success":true,"message":"Firewall revertido para o estado anterior."}'
