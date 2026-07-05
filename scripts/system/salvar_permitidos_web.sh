#!/bin/bash
# salvar_permitidos_web.sh <unidade1> [unidade2 ...]
# Grava a allowlist de servicos que o services_web.sh aceita gerenciar.
# Chamado via sudo pelo SystemServiceManager::salvarSelecao() quando o admin
# salva a tela de Configurar Servicos. Revalida cada nome (formato + existencia
# real no systemd) antes de gravar -- nao confia apenas na validacao do PHP.

ARQUIVO="/opt/rdtecnologia/scripts/.servicos_permitidos"

if [ "$#" -eq 0 ]; then
  echo "Nenhum servico informado"
  exit 1
fi

TMP="$(mktemp)"

for SERVICE in "$@"; do
  if ! [[ "$SERVICE" =~ ^[a-zA-Z0-9@_.-]+$ ]]; then
    echo "Servico invalido: $SERVICE"
    rm -f "$TMP"
    exit 1
  fi

  UNIT="${SERVICE}.service"
  if ! systemctl list-unit-files "$UNIT" --no-legend 2>/dev/null | grep -q "^${UNIT}[[:space:]]"; then
    echo "Servico nao encontrado: $SERVICE"
    rm -f "$TMP"
    exit 1
  fi

  echo "$SERVICE" >> "$TMP"
done

chmod 644 "$TMP"
mv "$TMP" "$ARQUIVO"

echo "OK"
