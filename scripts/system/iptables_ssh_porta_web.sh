#!/bin/bash
# iptables_ssh_porta_web.sh <nova_porta>
#
# Troca a porta do sshd (Port em /etc/ssh/sshd_config) e recarrega o
# servico. Faz backup do arquivo antes; o caminho do backup fica registrado
# pra iptables_rollback_web.sh tambem reverter o sshd_config junto com o
# firewall, caso a mudanca nao seja confirmada a tempo -- as duas coisas
# (firewall + sshd) sao tratadas como uma unica alteracao pendente.

set -u

PORTA="$1"

if ! [[ "$PORTA" =~ ^[0-9]+$ ]] || [ "$PORTA" -lt 1 ] || [ "$PORTA" -gt 65535 ]; then
  echo '{"success":false,"message":"Porta invalida."}'
  exit 1
fi

CONFIG="/etc/ssh/sshd_config"
mkdir -p /etc/rd-intranet/.iptables-backups
BACKUP="/etc/rd-intranet/.iptables-backups/sshd_config.bkp.$(date +%Y%m%d%H%M%S%N)"

cp "$CONFIG" "$BACKUP"

if grep -qE '^\s*Port\s+[0-9]+' "$CONFIG"; then
  sed -i -E "s/^\s*Port\s+[0-9]+/Port ${PORTA}/" "$CONFIG"
else
  printf '\nPort %s\n' "$PORTA" >> "$CONFIG"
fi

if ! sshd -t 2>/tmp/rd_sshd_err_$$; then
  ERRO="$(cat /tmp/rd_sshd_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_sshd_err_$$
  cp "$BACKUP" "$CONFIG"
  echo "{\"success\":false,\"message\":\"Configuracao de sshd invalida, revertida: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_sshd_err_$$

systemctl reload ssh 2>/dev/null || systemctl reload sshd 2>/dev/null

echo "$BACKUP" > /etc/rd-intranet/.iptables-ssh-backup-pendente

echo "{\"success\":true,\"message\":\"Porta do SSH alterada para ${PORTA} e servico recarregado.\"}"
