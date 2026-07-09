#!/bin/bash
# iptables_aplicar_web.sh <arquivo_ruleset_tmp> <segundos_rollback>
#
# Aplica um ruleset completo (secoes *filter e *nat, formato iptables-save)
# com backup do estado anterior e reversao automatica agendada -- mesmo
# padrao ja usado pra configuracao de rede (network_aplicar_web.sh): se a
# mudanca cortar o proprio acesso (SSH/HTTP), o servidor se autocorrige
# sozinho em ate <segundos_rollback> segundos.
#
# So mexe nas tabelas filter/nat (as unicas presentes no arquivo restaurado);
# mangle/raw ficam intocadas.

set -u

ORIGEM="$1"
SEGUNDOS="${2:-90}"

if [ ! -f "$ORIGEM" ]; then
  echo '{"success":false,"message":"Arquivo de origem nao encontrado."}'
  exit 1
fi

if ! [[ "$SEGUNDOS" =~ ^[0-9]+$ ]] || [ "$SEGUNDOS" -lt 15 ] || [ "$SEGUNDOS" -gt 600 ]; then
  SEGUNDOS=90
fi

mkdir -p /etc/rd-intranet/.iptables-backups

BACKUP="/etc/rd-intranet/.iptables-backups/rules.bkp.$(date +%Y%m%d%H%M%S%N)"
iptables-save > "$BACKUP" 2>/dev/null

# valida a sintaxe primeiro, sem tocar no kernel
if ! iptables-restore --test < "$ORIGEM" 2>/tmp/rd_ipt_err_$$; then
  ERRO="$(cat /tmp/rd_ipt_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_ipt_err_$$ "$BACKUP"
  echo "{\"success\":false,\"message\":\"Ruleset invalido, nada foi alterado: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_ipt_err_$$

if ! iptables-restore < "$ORIGEM" 2>/tmp/rd_ipt_err_$$; then
  ERRO="$(cat /tmp/rd_ipt_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_ipt_err_$$
  iptables-restore < "$BACKUP" 2>/dev/null
  echo "{\"success\":false,\"message\":\"Erro ao aplicar; estado anterior restaurado: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_ipt_err_$$

# Zera as entradas de conntrack de ICMP: o kernel trata uma sequencia de
# pings do mesmo id como "conexao estabelecida" por ate 30s (renovando a
# cada novo ping), e isso passa NA FRENTE de qualquer regra nova de
# bloqueio/log (a regra "ESTABLISHED,RELATED -> ACCEPT" tem que vir
# primeiro pra nao derrubar a propria sessao SSH/HTTP a cada aplicacao).
# Sem isso, um ping que ja estava rolando quando a regra mudou continua
# passando direto, mesmo com a regra nova ativa. So mexe em ICMP -- TCP/UDP
# (a sessao HTTP que esta chamando este script agora, por exemplo) nao sao
# tocados, pra nao arriscar cortar a propria requisicao em andamento.
if command -v conntrack >/dev/null 2>&1; then
  conntrack -D -p icmp >/dev/null 2>&1 || true
fi

# cancela qualquer rollback pendente anterior antes de agendar um novo
systemctl stop rd-iptables-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-iptables-rollback >/dev/null 2>&1

mkdir -p /etc/rd-intranet
echo "$(($(date +%s) + SEGUNDOS))" > /etc/rd-intranet/.iptables-deadline
echo "$BACKUP" > /etc/rd-intranet/.iptables-backup-pendente

systemd-run --unit=rd-iptables-rollback --on-active="$SEGUNDOS" \
  /opt/rdtecnologia/scripts/iptables_rollback_web.sh "$BACKUP" >/dev/null 2>&1

echo "{\"success\":true,\"message\":\"Firewall atualizado. Revertendo automaticamente em ${SEGUNDOS}s se nao for confirmado.\"}"
