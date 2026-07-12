#!/bin/bash
# vpn_wireguard_aplicar_web.sh <tmp_path> <interface>
# Aplica um wg0.conf gerado pela tela (PHP escreve em
# /etc/wireguard/rd/tmp/, root:www-data 775). Mesmo fluxo de
# backup/valida/aplica/reverte-se-invalido usado pro Samba
# (apply_shares_conf_web.sh), mas em JSON (padrao mais novo do app).
#
# Fonte da verdade e sempre o arquivo em disco: se a interface ja
# existir, sincroniza o estado ao vivo com "wg syncconf" (nao derruba
# tuneis existentes); se nao existir ainda, sobe com "wg-quick up".
# Nunca faz "wg set" avulso -- evita o estado ao vivo divergir do
# arquivo (ver comentario no plano/commit).

set -u

TEMP_FILE="${1:-}"
IFACE="${2:-wg0}"

if [ -z "$TEMP_FILE" ] || [ ! -f "$TEMP_FILE" ]; then
  echo '{"success":false,"message":"Arquivo temporário não encontrado."}'
  exit 1
fi

if ! [[ "$IFACE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo '{"success":false,"message":"Nome de interface inválido."}'
  exit 1
fi

CONF_ATIVO="/etc/wireguard/${IFACE}.conf"
BACKUP="/etc/wireguard/rd/backups/${IFACE}_$(date +%Y%m%d%H%M%S).conf"

if ! wg-quick strip "$TEMP_FILE" >/dev/null 2>/tmp/rd_vpnwg_err_$$; then
  ERRO="$(tail -10 /tmp/rd_vpnwg_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_vpnwg_err_$$
  echo "{\"success\":false,\"message\":\"Configuração inválida, nada foi alterado: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_vpnwg_err_$$

if [ -f "$CONF_ATIVO" ]; then
  cp "$CONF_ATIVO" "$BACKUP"
fi

cp "$TEMP_FILE" "$CONF_ATIVO"
chmod 600 "$CONF_ATIVO"

if ip link show "$IFACE" >/dev/null 2>&1; then
  # interface ja existe: aplica so o diff, sem derrubar peers conectados
  if ! wg syncconf "$IFACE" <(wg-quick strip "$CONF_ATIVO") 2>/tmp/rd_vpnwg_err_$$; then
    ERRO="$(tail -10 /tmp/rd_vpnwg_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_vpnwg_err_$$
    if [ -f "$BACKUP" ]; then
      cp "$BACKUP" "$CONF_ATIVO"
      wg syncconf "$IFACE" <(wg-quick strip "$CONF_ATIVO") >/dev/null 2>&1
    fi
    echo "{\"success\":false,\"message\":\"Falha ao aplicar, configuração anterior restaurada: ${ERRO}\"}"
    exit 1
  fi
else
  if ! wg-quick up "$CONF_ATIVO" 2>/tmp/rd_vpnwg_err_$$; then
    ERRO="$(tail -10 /tmp/rd_vpnwg_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_vpnwg_err_$$
    rm -f "$CONF_ATIVO"
    echo "{\"success\":false,\"message\":\"Falha ao subir a interface: ${ERRO}\"}"
    exit 1
  fi
fi
rm -f /tmp/rd_vpnwg_err_$$

echo "{\"success\":true,\"message\":\"Configuração aplicada com sucesso.\"}"
