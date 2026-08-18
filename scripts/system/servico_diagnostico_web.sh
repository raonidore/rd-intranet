#!/bin/bash
# servico_diagnostico_web.sh <unidade>
# So LEITURA (ultimas linhas do journalctl) -- usado pela tela de
# "Configurar Servicos" pra mostrar o motivo real de uma unidade em falha
# ANTES dela estar na lista de servicos gerenciados (o proprio proposito da
# tela e' triagem antes de aprovar). Diferente de services_web.sh
# (restart/reload/logs, restritos a allowlist), aqui nao ha allowlist -- so
# formato do nome + existencia real da unidade, porque journalctl e'
# read-only e nao muda estado nenhum do sistema.

UNIT_RAW="$1"

if ! [[ "$UNIT_RAW" =~ ^[a-zA-Z0-9@_.-]+$ ]]; then
  echo "Servico invalido"
  exit 1
fi

UNIT="${UNIT_RAW}.service"

if ! systemctl list-unit-files "$UNIT" --no-legend 2>/dev/null | grep -q "^${UNIT}[[:space:]]"; then
  echo "Servico nao encontrado"
  exit 1
fi

journalctl -u "$UNIT" -n 20 --no-pager --no-hostname
