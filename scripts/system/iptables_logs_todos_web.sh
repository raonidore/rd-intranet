#!/bin/bash
# iptables_logs_todos_web.sh <desde_epoch>
# Somente leitura: linhas do log do kernel com prefixo "RD-FW-" (qualquer
# regra, todas de uma vez -- mais barato que perguntar regra por regra)
# desde o timestamp unix informado. Usado pelo coletor do ranking de IPs
# mais bloqueados (coletar_logs_iptables.php).

set -u

DESDE="$1"

if ! [[ "$DESDE" =~ ^[0-9]+$ ]]; then
  echo "Timestamp invalido" >&2
  exit 1
fi

if command -v journalctl >/dev/null 2>&1; then
  journalctl -k --since "@${DESDE}" --no-pager 2>/dev/null | grep -F -- "RD-FW-"
else
  dmesg 2>/dev/null | grep -F -- "RD-FW-"
fi
