#!/bin/bash
# dhcp_aplicar_web.sh <arquivo_dhcpd_conf_tmp> <interface> <segundos_rollback>
#
# Aplica um dhcpd.conf novo com validacao de sintaxe (dhcpd -t -cf, o
# equivalente do "testparm -s"/"iptables-restore --test" ja usados
# neste projeto), backup do estado anterior e reversao automatica
# agendada -- mesmo esqueleto de scripts/system/iptables_aplicar_web.sh:
# se a mudanca derrubar o servico ou entregar IP errado pra rede
# inteira, o servidor se autocorrige sozinho em ate <segundos_rollback>
# segundos, sem precisar de ninguem confirmar a tempo.
#
# Tambem escreve /etc/default/isc-dhcp-server (INTERFACESv4) -- e
# ESSENCIAL: um dhcpd.conf perfeito nao serve de nada se o daemon nao
# estiver escutando na interface certa. Syntax-check (dhcpd -t) NAO
# valida isso, so a sintaxe do .conf -- por isso a interface e
# conferida separadamente (precisa existir de verdade na maquina) antes
# de qualquer coisa.

set -u

ORIGEM="$1"
IFACE="${2:-}"
SEGUNDOS="${3:-90}"

if [ ! -f "$ORIGEM" ]; then
  echo '{"success":false,"message":"Arquivo de configuracao nao encontrado."}'
  exit 1
fi

if [ -z "$IFACE" ] || ! ip link show "$IFACE" >/dev/null 2>&1; then
  echo "{\"success\":false,\"message\":\"Interface '${IFACE}' nao existe nesta maquina.\"}"
  exit 1
fi

if ! [[ "$SEGUNDOS" =~ ^[0-9]+$ ]] || [ "$SEGUNDOS" -lt 15 ] || [ "$SEGUNDOS" -gt 600 ]; then
  SEGUNDOS=90
fi

CONF="/etc/dhcp/dhcpd.conf"
DEFAULTS="/etc/default/isc-dhcp-server"

# dhcpd roda confinado por AppArmor (perfil usr.sbin.dhcpd) -- só pode ler
# dentro de /etc/dhcp/, NUNCA /tmp/ (confirmado ao vivo: "dhcpd -t -cf
# /tmp/arquivo" falha com "Permission denied" mesmo rodando como root,
# porque AppArmor é controle obrigatório, independente de dono/permissão
# Unix do arquivo). Por isso copia pra dentro de /etc/dhcp/ ANTES de
# validar, em vez de apontar o -cf direto pro caminho que o PHP gerou.
ARQUIVO_TESTE="/etc/dhcp/.rd-intranet-teste.conf"
cp "$ORIGEM" "$ARQUIVO_TESTE"

if ! dhcpd -t -cf "$ARQUIVO_TESTE" >/tmp/rd_dhcp_err_$$ 2>&1; then
  ERRO="$(tail -15 /tmp/rd_dhcp_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_dhcp_err_$$ "$ARQUIVO_TESTE"
  echo "{\"success\":false,\"message\":\"Configuracao invalida, nada foi alterado: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_dhcp_err_$$ "$ARQUIVO_TESTE"

mkdir -p /etc/rd-intranet/.dhcp-backups
CARIMBO="$(date +%Y%m%d%H%M%S%N)"
BACKUP_CONF="/etc/rd-intranet/.dhcp-backups/dhcpd.conf.bkp.${CARIMBO}"
BACKUP_DEFAULTS="/etc/rd-intranet/.dhcp-backups/isc-dhcp-server.bkp.${CARIMBO}"

[ -f "$CONF" ] && cp "$CONF" "$BACKUP_CONF" || touch "$BACKUP_CONF.inexistente"
[ -f "$DEFAULTS" ] && cp "$DEFAULTS" "$BACKUP_DEFAULTS" || touch "$BACKUP_DEFAULTS.inexistente"

# Estado do servico ANTES de aplicar -- se estava parado, o rollback
# deve deixar parado de novo (nao ligar sozinho um servico que o admin
# tinha desligado de proposito).
if systemctl is-active --quiet isc-dhcp-server; then
  ESTAVA_ATIVO=1
else
  ESTAVA_ATIVO=0
fi
echo "$ESTAVA_ATIVO" > "/etc/rd-intranet/.dhcp-backups/estava-ativo.${CARIMBO}"

cp "$ORIGEM" "$CONF"
echo "INTERFACESv4=\"${IFACE}\"" > "$DEFAULTS"

systemctl restart isc-dhcp-server 2>/tmp/rd_dhcp_err_$$
RESTART_RC=$?
# dhcpd pode subir (Type=simple reporta "active" no fork) e cair sozinho
# poucos milissegundos depois -- ex: interface sem nenhuma subnet
# declarada pra rede dela ("No subnet declaration for eth0 (x.x.x.x)").
# Confirmado ao vivo: sem essa espera, o "is-active" logo em seguida
# ainda pega o servico de pe e a falha so aparece na janela de rollback
# (ate 600s de DHCP fora do ar), em vez de ser revertida na hora.
sleep 2
if [ "$RESTART_RC" -ne 0 ] || ! systemctl is-active --quiet isc-dhcp-server; then
  ERRO="$(journalctl -u isc-dhcp-server -n 15 --no-pager 2>/dev/null | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_dhcp_err_$$
  # restaura na hora, nao espera a janela de rollback pra um erro ja detectado agora
  [ -f "$BACKUP_CONF" ] && cp "$BACKUP_CONF" "$CONF" || rm -f "$CONF"
  [ -f "$BACKUP_DEFAULTS" ] && cp "$BACKUP_DEFAULTS" "$DEFAULTS" || rm -f "$DEFAULTS"
  if [ "$ESTAVA_ATIVO" = "1" ]; then
    systemctl restart isc-dhcp-server >/dev/null 2>&1
  else
    systemctl stop isc-dhcp-server >/dev/null 2>&1
    systemctl reset-failed isc-dhcp-server >/dev/null 2>&1
  fi
  echo "{\"success\":false,\"message\":\"Servico nao subiu com a config nova, revertido na hora: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_dhcp_err_$$

# cancela qualquer rollback pendente anterior antes de agendar um novo
systemctl stop rd-dhcp-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-dhcp-rollback >/dev/null 2>&1

mkdir -p /etc/rd-intranet
echo "$(($(date +%s) + SEGUNDOS))" > /etc/rd-intranet/.dhcp-deadline
echo "${BACKUP_CONF}|${BACKUP_DEFAULTS}|${ESTAVA_ATIVO}" > /etc/rd-intranet/.dhcp-backup-pendente

systemd-run --unit=rd-dhcp-rollback --on-active="$SEGUNDOS" \
  /opt/rdtecnologia/scripts/dhcp_rollback_web.sh "${BACKUP_CONF}|${BACKUP_DEFAULTS}|${ESTAVA_ATIVO}" >/dev/null 2>&1

echo "{\"success\":true,\"message\":\"DHCP atualizado e servico rodando na interface ${IFACE}. Revertendo automaticamente em ${SEGUNDOS}s se nao for confirmado.\"}"
