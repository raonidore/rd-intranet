#!/bin/bash
# repositorio_agente_regras_git.sh
#
# Lê no repositório git (branch main) a versão do agente Windows publicada
# (agente-windows/dist/VERSION.txt) e as regras de comportamento do
# anti-ransomware (config/seguranca-regras.json) -- usado pela sincronização
# automática (rd agente:sincronizar, cron a cada 30 min).
#
# Mesmo cuidado do agente_baixar_git.sh: só LEITURA do git (fetch + show),
# nunca mexe na working tree -- o sistema que está rodando neste servidor
# não muda; só esses dois dados chegam antes de uma atualização completa.
#
# Saída: JSON {"success":true,"versao_agente":"1.0.50","regras_base64":"..."}

set -u

REPO_DIR="/var/www/rd.intranet"
REPO_USER="ti"
BRANCH="main"

cd "$REPO_DIR" || { echo '{"success":false,"message":"Diretorio do repositorio nao encontrado."}'; exit 1; }

SAIDA_FETCH=$(sudo -u "$REPO_USER" git fetch origin "$BRANCH" --quiet 2>&1)
if [ $? -ne 0 ]; then
  echo "{\"success\":false,\"message\":\"Erro ao buscar atualizacoes do repositorio: ${SAIDA_FETCH//\"/\\\"}\"}"
  exit 1
fi

VERSAO=$(sudo -u "$REPO_USER" git show "origin/${BRANCH}:agente-windows/dist/VERSION.txt" 2>/dev/null | tr -d '[:space:]')
REGRAS=$(sudo -u "$REPO_USER" git show "origin/${BRANCH}:config/seguranca-regras.json" 2>/dev/null | base64 -w0)

# versão só com dígitos e pontos -- vai direto pro JSON de saída
case "$VERSAO" in
  *[!0-9.]*) VERSAO="" ;;
esac

echo "{\"success\":true,\"versao_agente\":\"${VERSAO}\",\"regras_base64\":\"${REGRAS}\"}"
