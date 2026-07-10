#!/bin/bash
# atualizar_reverter_web.sh <sha>
#
# Reverte o codigo do servidor para um commit especifico do historico local
# (git reset --hard). E a unica operacao destrutiva deste modulo -- por isso
# so aceita um SHA de 40 caracteres hex que ja existe no historico local
# (nunca um branch, tag ou nome vindo direto do formulario). O PHP so chama
# este script com o commit_antes gravado em atualizacoes_log pela propria
# aplicacao, nunca com um valor digitado livremente na tela.

set -u

REPO_DIR="/var/www/rd.intranet"
REPO_USER="ti"
SHA="${1:-}"

if ! [[ "$SHA" =~ ^[0-9a-f]{40}$ ]]; then
  echo '{"success":false,"message":"Commit invalido."}'
  exit 1
fi

cd "$REPO_DIR" || { echo '{"success":false,"message":"Diretorio do repositorio nao encontrado."}'; exit 1; }

if ! sudo -u "$REPO_USER" git cat-file -e "${SHA}^{commit}" 2>/dev/null; then
  echo '{"success":false,"message":"Commit nao encontrado no historico local."}'
  exit 1
fi

SAIDA=$(sudo -u "$REPO_USER" git reset --hard "$SHA" 2>&1)
if [ $? -ne 0 ]; then
  echo "{\"success\":false,\"message\":\"Erro ao reverter: ${SAIDA//\"/\\\"}\"}"
  exit 1
fi

SAIDA_SYNC=$(bash "$REPO_DIR/scripts/sync-system-scripts.sh" 2>&1)
if [ $? -ne 0 ]; then
  echo "{\"success\":false,\"message\":\"Revertido, mas falhou ao sincronizar scripts de sistema: ${SAIDA_SYNC//\"/\\\"}\"}"
  exit 1
fi

echo '{"success":true,"message":"Revertido com sucesso."}'
