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
#      rotação, cresce sem limite. So cria o arquivo com um padrao (30
#      dias) na primeira instalacao -- se o admin ja mudou a retencao
#      pela tela (Samba > Auditoria), samba_auditoria_retencao_web.sh
#      e o UNICO autorizado a reescrever "rotate N" depois disso, pra
#      "Aplicar atualizacao" nunca apagar essa escolha sem querer.

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

# SambaGlobalConfigService::gerarSmbConf() ja escreve esse include em
# qualquer regeneracao NOVA do smb.conf -- mas um servidor que ja
# tinha o [global] gerado ANTES dessa mudanca so ganha a linha de novo
# se um admin reabrir e salvar Samba > Config Global manualmente (sem
# motivo nenhum pra saber que precisa fazer isso). Idempotente: so
# insere se realmente estiver faltando, logo apos o include do
# antivirus (mesma posicao que gerarSmbConf() usa).
if [ -f /etc/samba/smb.conf ] && grep -q "^include = /etc/samba/antivirus.conf$" /etc/samba/smb.conf \
   && ! grep -q "^include = ${ARQUIVO_AUDIT_CONF}$" /etc/samba/smb.conf; then
  cp /etc/samba/smb.conf /etc/samba/smb.conf.antes-auditoria
  sed -i "\\#^include = /etc/samba/antivirus.conf\$#a include = ${ARQUIVO_AUDIT_CONF}" /etc/samba/smb.conf
  if testparm -s >/dev/null 2>&1; then
    systemctl reload smbd 2>/dev/null || systemctl restart smbd
  else
    mv /etc/samba/smb.conf.antes-auditoria /etc/samba/smb.conf
  fi
  rm -f /etc/samba/smb.conf.antes-auditoria
fi

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

if [ ! -f "$ARQUIVO_LOGROTATE" ]; then
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
fi

systemctl restart rsyslog

echo "OK"
