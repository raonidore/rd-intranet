#!/bin/bash
# vpn_wireguard_saida_web.sh <acao> <nome> [tmp_path]
# Gerencia conexoes WireGuard de SAIDA (este servidor como peer/cliente
# de um WireGuard existente de terceiros) via o template systemd
# "wg-quick@" que o proprio pacote wireguard-tools fornece (le
# /etc/wireguard/<nome>.conf). Mesma estrutura de
# vpn_openvpn_saida_web.sh.
#
# Acoes:
#   aplicar <nome> <tmp_path>   grava o .conf (dono root, so leitura)
#   conectar <nome>              wg-quick up (via systemd)
#   desconectar <nome>           wg-quick down (via systemd)
#   status <nome>                 ativo/inativo
#   remover <nome>                para, desabilita, apaga o arquivo

set -u

ACAO="${1:-}"
NOME="${2:-}"

if ! [[ "$NOME" =~ ^[a-zA-Z0-9_-]{1,15}$ ]]; then
  echo '{"success":false,"message":"Nome de conexão inválido."}'
  exit 1
fi

CONF="/etc/wireguard/${NOME}.conf"
UNIDADE="wg-quick@${NOME}"

case "$ACAO" in
  aplicar)
    TMP="${3:-}"
    if [ -z "$TMP" ] || [ ! -f "$TMP" ]; then
      echo '{"success":false,"message":"Arquivo temporário não encontrado."}'
      exit 1
    fi
    if ! wg-quick strip "$TMP" >/dev/null 2>/tmp/rd_vpnwg_saida_err_$$; then
      ERRO="$(tail -10 /tmp/rd_vpnwg_saida_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
      rm -f /tmp/rd_vpnwg_saida_err_$$
      echo "{\"success\":false,\"message\":\"Configuração inválida: ${ERRO}\"}"
      exit 1
    fi
    rm -f /tmp/rd_vpnwg_saida_err_$$
    mkdir -p /etc/wireguard
    cp "$TMP" "$CONF"
    chmod 600 "$CONF"
    echo '{"success":true,"message":"Configuração salva."}'
    ;;

  conectar)
    if [ ! -f "$CONF" ]; then
      echo '{"success":false,"message":"Configuração não encontrada."}'
      exit 1
    fi
    systemctl start "$UNIDADE"
    sleep 1
    if systemctl is-active --quiet "$UNIDADE"; then
      echo '{"success":true,"message":"Conectado."}'
    else
      ERRO="$(journalctl -u "$UNIDADE" -n 15 --no-pager 2>/dev/null | tr '\n' ' ' | sed 's/"/\\"/g')"
      echo "{\"success\":false,\"message\":\"Falha ao conectar: ${ERRO}\"}"
    fi
    ;;

  desconectar)
    systemctl stop "$UNIDADE"
    echo '{"success":true,"message":"Desconectado."}'
    ;;

  status)
    if systemctl is-active --quiet "$UNIDADE"; then
      echo "ATIVO|1"
    else
      echo "ATIVO|0"
    fi
    ;;

  ativar_boot)
    systemctl enable "$UNIDADE" >/dev/null 2>&1
    echo '{"success":true,"message":"Ativado no boot."}'
    ;;

  desativar_boot)
    systemctl disable "$UNIDADE" >/dev/null 2>&1
    echo '{"success":true,"message":"Desativado no boot."}'
    ;;

  remover)
    systemctl stop "$UNIDADE" >/dev/null 2>&1
    systemctl disable "$UNIDADE" >/dev/null 2>&1
    rm -f "$CONF"
    echo '{"success":true,"message":"Conexão removida."}'
    ;;

  *)
    echo '{"success":false,"message":"Ação desconhecida."}'
    exit 1
    ;;
esac
