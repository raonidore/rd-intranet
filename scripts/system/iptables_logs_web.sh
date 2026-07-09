#!/bin/bash
# iptables_logs_web.sh <prefixo>
# Somente leitura: ultimas linhas do log do kernel que batem com o prefixo
# de uma regra especifica (gravado via alvo LOG do iptables quando
# "registrar_log" esta ligado na regra). Usado pra tela mostrar quais IPs
# estao sendo bloqueados/liberados por aquela regra.

set -u

PREFIXO="$1"

# só letras, numeros, hifen e dois-pontos (formato "RD-FW-<id>:")
if ! [[ "$PREFIXO" =~ ^RD-FW-[0-9]+:$ ]]; then
  echo "Prefixo invalido" >&2
  exit 1
fi

if command -v journalctl >/dev/null 2>&1; then
  journalctl -k -n 500 --no-pager 2>/dev/null | grep -F -- "$PREFIXO"
else
  dmesg 2>/dev/null | grep -F -- "$PREFIXO" | tail -n 500
fi
