#!/bin/bash
# network_aplicar_web.sh <interface> <modo:dhcp|estatico> <ip_cidr|-> <gateway|-> <dns_csv|->
#
# Grava um netplan de override (90-rd-intranet.yaml, prioridade maior que o
# 50-cloud-init.yaml) e aplica. Agenda uma reversao automatica em 120s via
# systemd-run, cancelavel por network_confirmar_web.sh — se a mudanca
# derrubar o acesso, o servidor se autocorrige sozinho.

set -u

IFACE="$1"
MODO="$2"
IP_CIDR="$3"
GATEWAY="$4"
DNS_CSV="$5"

CONFIG="/etc/netplan/90-rd-intranet.yaml"
BACKUP_DIR="/etc/netplan/.rd-backups"
mkdir -p "$BACKUP_DIR"

if ! ip link show "$IFACE" >/dev/null 2>&1; then
  echo '{"success":false,"message":"Interface nao encontrada."}'
  exit 1
fi

BACKUP=""
if [ -f "$CONFIG" ]; then
  BACKUP="$BACKUP_DIR/90-rd-intranet.yaml.bkp.$(date +%Y%m%d%H%M%S)"
  cp "$CONFIG" "$BACKUP"
fi

# Gateway e DNS são opcionais: interface só pra rede interna (ex.: segunda
# placa falando com equipamentos de outra faixa) NÃO deve ter rota default --
# duas rotas default (uma por placa) bagunçam a saída pra internet.
[ "$GATEWAY" = "-" ] && GATEWAY=""
[ "$DNS_CSV" = "-" ] && DNS_CSV=""

# Reescreve SÓ a interface editada e preserva as outras que já estão no
# arquivo -- antes ele era sobrescrito inteiro, e configurar a 2ª placa
# apagava o override da 1ª (que voltava pro 50-cloud-init, às vezes DHCP).
if command -v python3 >/dev/null 2>&1 && python3 -c 'import yaml' >/dev/null 2>&1; then
  if ! python3 - "$CONFIG" "$IFACE" "$MODO" "$IP_CIDR" "$GATEWAY" "$DNS_CSV" <<'PY'
import os, sys, yaml
config, iface, modo, ip_cidr, gateway, dns_csv = sys.argv[1:7]
dados = {}
if os.path.exists(config):
    with open(config) as f:
        dados = yaml.safe_load(f) or {}
rede = dados.setdefault('network', {})
rede['version'] = 2
ethernets = rede.setdefault('ethernets', {}) or {}
rede['ethernets'] = ethernets
if modo == 'dhcp':
    ethernets[iface] = {'dhcp4': True}
else:
    item = {'dhcp4': False, 'addresses': [ip_cidr]}
    if gateway:
        item['routes'] = [{'to': 'default', 'via': gateway}]
    dns = [d.strip() for d in dns_csv.split(',') if d.strip()]
    if dns:
        item['nameservers'] = {'addresses': dns}
    ethernets[iface] = item
with open(config, 'w') as f:
    yaml.safe_dump(dados, f, default_flow_style=False, sort_keys=False)
PY
  then
    if [ -n "$BACKUP" ]; then cp "$BACKUP" "$CONFIG"; fi
    echo '{"success":false,"message":"Falha ao montar o netplan."}'
    exit 1
  fi
else
  # Sem python3-yaml: só dá pra escrever o arquivo inteiro com esta interface.
  if [ -f "$CONFIG" ] && grep -E '^    [A-Za-z0-9._-]+:$' "$CONFIG" | grep -qv "^    $IFACE:$"; then
    echo '{"success":false,"message":"O arquivo de rede tem outras interfaces e o servidor nao tem python3-yaml para preserva-las. Instale python3-yaml."}'
    exit 1
  fi
  {
    echo "network:"
    echo "  version: 2"
    echo "  ethernets:"
    echo "    $IFACE:"
    if [ "$MODO" = "dhcp" ]; then
      echo "      dhcp4: true"
    else
      echo "      dhcp4: false"
      echo "      addresses: [$IP_CIDR]"
      if [ -n "$GATEWAY" ]; then
        echo "      routes:"
        echo "        - to: default"
        echo "          via: $GATEWAY"
      fi
      DNS_YAML="$(echo "$DNS_CSV" | tr ',' '\n' | xargs | tr ' ' ',' | sed 's/,/, /g')"
      if [ -n "$DNS_YAML" ]; then
        echo "      nameservers:"
        echo "        addresses: [$DNS_YAML]"
      fi
    fi
  } > "$CONFIG"
fi

chmod 600 "$CONFIG"

ERR_FILE="/tmp/rd_netplan_err_$$"
if ! netplan generate 2>"$ERR_FILE"; then
  ERRO="$(cat "$ERR_FILE" | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f "$ERR_FILE"
  if [ -n "$BACKUP" ]; then cp "$BACKUP" "$CONFIG"; else rm -f "$CONFIG"; fi
  netplan generate >/dev/null 2>&1
  echo "{\"success\":false,\"message\":\"Configuracao invalida: ${ERRO}\"}"
  exit 1
fi
rm -f "$ERR_FILE"

netplan apply

# cancela qualquer rollback pendente anterior antes de agendar um novo
systemctl stop rd-netplan-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-netplan-rollback >/dev/null 2>&1

# prazo gravado em arquivo (timestamp absoluto) -- consultar propriedades do
# systemd-run pra descobrir "quanto falta" e fragil: --on-active cria um timer
# MONOTONICO, cuja info de proximo disparo vem em NextElapseUSecMonotonic (uma
# duracao relativa ao boot), nao em NextElapseUSecRealtime (isso e so pra
# timers de calendario). Um arquivo com o timestamp absoluto e muito mais
# simples de checar depois.
mkdir -p /etc/rd-intranet
echo "$(($(date +%s) + 120))" > /etc/rd-intranet/.rede-deadline

systemd-run --unit=rd-netplan-rollback --on-active=120 \
  /opt/rdtecnologia/scripts/network_rollback_web.sh "$CONFIG" "$BACKUP" >/dev/null 2>&1

echo '{"success":true,"message":"Configuracao aplicada. Revertendo automaticamente em 120s se nao for confirmada."}'
