#!/bin/bash
# vpn_ikev2_conexao_web.sh <acao> <nome>
# Sobe/derruba uma conexao especifica JA CARREGADA no ipsec.conf (tanto
# a do modo servidor quanto qualquer uma de saida) -- diferente do
# OpenVPN/WireGuard, o strongSwan roda todas as conexoes no mesmo
# daemon, "ipsec up/down <nome>" so ativa/desativa aquele tunel
# especifico, nao precisa de unidade systemd separada por conexao.
#
# Acoes: up <nome> | down <nome> | status <nome>

set -u

ACAO="${1:-}"
NOME="${2:-}"

if ! [[ "$NOME" =~ ^[a-zA-Z0-9_-]{1,64}$ ]]; then
  echo '{"success":false,"message":"Nome de conexão inválido."}'
  exit 1
fi

case "$ACAO" in
  up)
    if ipsec up "$NOME" >/tmp/rd_ikev2_conn_err_$$ 2>&1; then
      rm -f /tmp/rd_ikev2_conn_err_$$
      echo '{"success":true,"message":"Conectado."}'
    else
      ERRO="$(tail -15 /tmp/rd_ikev2_conn_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
      rm -f /tmp/rd_ikev2_conn_err_$$
      echo "{\"success\":false,\"message\":\"Falha ao conectar: ${ERRO}\"}"
    fi
    ;;

  down)
    ipsec down "$NOME" >/dev/null 2>&1
    echo '{"success":true,"message":"Desconectado."}'
    ;;

  status)
    if ipsec status 2>/dev/null | grep -q "^${NOME}\[.*ESTABLISHED"; then
      echo "ATIVO|1"
    else
      echo "ATIVO|0"
    fi
    ;;

  *)
    echo '{"success":false,"message":"Ação desconhecida."}'
    exit 1
    ;;
esac
