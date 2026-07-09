#!/bin/bash
# iptables_contadores_web.sh
# Somente leitura: contadores de pacotes/bytes por regra (pra tela de
# Firewall Ao Vivo mostrar, em tempo real, uma regra bloqueando trafego).
# Separado do iptables_status_web.sh (que faz o dump completo via
# iptables-save) pra manter o polling barato.

echo "=== CONTADORES-FILTER ==="
iptables -L -n -v -x --line-numbers 2>&1

echo "=== CONTADORES-NAT ==="
iptables -t nat -L -n -v -x --line-numbers 2>&1
