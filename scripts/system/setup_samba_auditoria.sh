#!/bin/bash
# setup_samba_auditoria.sh
# Passo de instalacao/atualizacao (idempotente, roda como root). Prepara
# a infraestrutura de log da Auditoria de Arquivos (Samba > Auditoria),
# SEM ativar a auditoria em si (isso é feito por
# samba_auditoria_web.sh, sob demanda, quando o admin liga na tela):
#
#   1. /etc/samba/audit.conf precisa EXISTIR (mesmo vazio) pra o
#      "include" no smb.conf nunca apontar pra um arquivo inexistente.
#   2. Roteamento de syslog: o modulo full_audit manda a mensagem pela
#      facility LOCAL5 (ver samba_auditoria_web.sh) -- sem uma regra
#      dedicada, ela cai no /var/log/syslog geral, junto de tudo mais.
#      "& stop" evita registrar em duplicidade.
#   3. O arquivo de destino precisa nascer dono syslog:adm (mesmo
#      esquema de /var/log/auth.log) -- criado root:root (ou synced
#      antes do processo rsyslogd existir) faz o rsyslog aceitar a
#      mensagem em silencio e NUNCA escrever nele, sem erro nenhum
#      visivel em lugar nenhum. Confirmado ao vivo -- essa exata causa
#      já perdeu um teste inteiro antes de ser encontrada.
#   4. logrotate: log de auditoria pode crescer rápido (uma gravação de
#      arquivo grande já gera várias linhas via pwrite_recv) -- sem
#      rotação, cresce sem limite.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

ARQUIVO_AUDIT_CONF="/etc/samba/audit.conf"
ARQUIVO_RSYSLOG="/etc/rsyslog.d/49-samba-audit.conf"
ARQUIVO_LOG="/var/log/samba/audit.log"
ARQUIVO_LOGROTATE="/etc/logrotate.d/samba-audit"

[ -f "$ARQUIVO_AUDIT_CONF" ] || touch "$ARQUIVO_AUDIT_CONF"
chown root:root "$ARQUIVO_AUDIT_CONF"
chmod 644 "$ARQUIVO_AUDIT_CONF"

cat > "$ARQUIVO_RSYSLOG" <<EOF
# Gerado pela RD Intranet -- roteia o log da Auditoria de Arquivos
# (Samba > Auditoria, modulo VFS full_audit) pra um arquivo proprio, em
# vez de misturar com o syslog geral.
local5.* ${ARQUIVO_LOG}
& stop
EOF

mkdir -p /var/log/samba
[ -f "$ARQUIVO_LOG" ] || touch "$ARQUIVO_LOG"
chown syslog:adm "$ARQUIVO_LOG"
chmod 640 "$ARQUIVO_LOG"

cat > "$ARQUIVO_LOGROTATE" <<EOF
${ARQUIVO_LOG} {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 640 syslog adm
    postrotate
        systemctl kill -s HUP rsyslog.service >/dev/null 2>&1 || true
    endscript
}
EOF

systemctl restart rsyslog

echo "OK"
