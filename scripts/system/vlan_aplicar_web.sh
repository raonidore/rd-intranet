#!/bin/bash
# vlan_aplicar_web.sh <arquivo_yaml_tmp_ou_-> <segundos_rollback>
#
# Grava um netplan de override (95-rd-intranet-vlans.yaml) com TODAS as
# VLANs ativas cadastradas em Infraestrutura > VLANs e aplica -- mesmo
# esqueleto de scripts/system/network_aplicar_web.sh (que edita o IP da
# interface física): valida sintaxe, faz backup do estado anterior, e
# agenda reversao automatica via systemd-run. Mesmo risco que o editor de
# Interfaces: uma VLAN mal configurada na interface que você usa pra
# acessar o servidor pode derrubar seu proprio acesso -- por isso a
# mesma rede de seguranca.
#
# Se o primeiro argumento for "-", significa que não sobrou nenhuma VLAN
# ativa cadastrada (todas foram excluídas) -- remove o arquivo de
# override em vez de gravar um novo, o que efetivamente desfaz todas as
# VLANs criadas por este módulo.

set -u

ORIGEM="$1"
SEGUNDOS="${2:-90}"

if ! [[ "$SEGUNDOS" =~ ^[0-9]+$ ]] || [ "$SEGUNDOS" -lt 15 ] || [ "$SEGUNDOS" -gt 600 ]; then
  SEGUNDOS=90
fi

CONFIG="/etc/netplan/95-rd-intranet-vlans.yaml"
BACKUP_DIR="/etc/netplan/.rd-backups"
mkdir -p "$BACKUP_DIR"

# Extrai os nomes de interface declarados sob "vlans:" num yaml gerado por
# este modulo (indentacao fixa de 4 espacos -- ver VlanService::gerarConteudoNetplan()).
extrair_vlans() {
  grep -E '^    [A-Za-z0-9_.@-]+:$' "$1" 2>/dev/null | sed -E 's/^    //; s/:$//'
}

ANTIGAS=""
BACKUP=""
if [ -f "$CONFIG" ]; then
  ANTIGAS="$(extrair_vlans "$CONFIG")"
  BACKUP="$BACKUP_DIR/95-rd-intranet-vlans.yaml.bkp.$(date +%Y%m%d%H%M%S%N)"
  cp "$CONFIG" "$BACKUP"
fi

if [ "$ORIGEM" = "-" ]; then
  rm -f "$CONFIG"
else
  if [ ! -f "$ORIGEM" ]; then
    echo '{"success":false,"message":"Arquivo de configuracao nao encontrado."}'
    exit 1
  fi
  cp "$ORIGEM" "$CONFIG"
  chmod 600 "$CONFIG"
fi

ERR_FILE="/tmp/rd_vlan_err_$$"
if ! netplan generate 2>"$ERR_FILE"; then
  ERRO="$(cat "$ERR_FILE" | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f "$ERR_FILE"
  if [ -n "$BACKUP" ]; then cp "$BACKUP" "$CONFIG"; else rm -f "$CONFIG"; fi
  netplan generate >/dev/null 2>&1
  echo "{\"success\":false,\"message\":\"Configuracao invalida, nada foi alterado: ${ERRO}\"}"
  exit 1
fi
rm -f "$ERR_FILE"

NOVAS=""
[ -f "$CONFIG" ] && NOVAS="$(extrair_vlans "$CONFIG")"

netplan apply

# netplan NAO remove interfaces que saem da configuracao -- limitacao
# conhecida, confirmada ao vivo: depois de excluir uma VLAN e aplicar (ou
# reverter), a sub-interface continuava existindo de verdade (ip link
# show) mesmo com o arquivo de override ja atualizado/removido. Apaga na
# unha cada uma que existia antes e nao existe mais na config nova.
while IFS= read -r IFACE_ANTIGA; do
  [ -z "$IFACE_ANTIGA" ] && continue
  if ! printf '%s\n' "$NOVAS" | grep -qxF "$IFACE_ANTIGA"; then
    ip link delete "$IFACE_ANTIGA" >/dev/null 2>&1
  fi
done <<< "$ANTIGAS"

# cancela qualquer rollback pendente anterior antes de agendar um novo
systemctl stop rd-vlan-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-vlan-rollback >/dev/null 2>&1

mkdir -p /etc/rd-intranet
echo "$(($(date +%s) + SEGUNDOS))" > /etc/rd-intranet/.vlan-deadline
echo "${CONFIG}|${BACKUP}" > /etc/rd-intranet/.vlan-backup-pendente

systemd-run --unit=rd-vlan-rollback --on-active="$SEGUNDOS" \
  /opt/rdtecnologia/scripts/vlan_rollback_web.sh "$CONFIG" "$BACKUP" >/dev/null 2>&1

echo "{\"success\":true,\"message\":\"VLANs aplicadas. Revertendo automaticamente em ${SEGUNDOS}s se nao for confirmado.\"}"
