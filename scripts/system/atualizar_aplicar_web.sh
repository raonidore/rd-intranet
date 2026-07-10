#!/bin/bash
# atualizar_aplicar_web.sh
#
# Busca e aplica (fast-forward apenas) a atualizacao mais recente de
# origin/main, sincroniza os scripts de sistema e reinstala dependencias do
# composer se o composer.lock mudou. Nunca faz reset/force -- se a arvore
# local nao puder avancar em fast-forward (historico divergente), falha e
# nao mexe em nada, pra nao arriscar perder trabalho local no servidor.
# Le e escreve o codigo como o dono do checkout ('sudo -u'), nunca como
# root direto, pra nao acabar deixando arquivo root-owned no meio do repo.

set -u

REPO_DIR="/var/www/rd.intranet"
REPO_USER="ti"
BRANCH="main"
SYNC_SCRIPTS="$REPO_DIR/scripts/sync-system-scripts.sh"

cd "$REPO_DIR" || { echo '{"success":false,"message":"Diretorio do repositorio nao encontrado."}'; exit 1; }

LOCK_ANTES=""
[ -f composer.lock ] && LOCK_ANTES="$(sha1sum composer.lock)"

SAIDA_FETCH=$(sudo -u "$REPO_USER" git fetch origin "$BRANCH" --quiet 2>&1)
if [ $? -ne 0 ]; then
  echo "{\"success\":false,\"message\":\"Erro ao buscar atualizacoes: ${SAIDA_FETCH//\"/\\\"}\"}"
  exit 1
fi

SAIDA_MERGE=$(sudo -u "$REPO_USER" git merge --ff-only "origin/$BRANCH" 2>&1)
if [ $? -ne 0 ]; then
  echo "{\"success\":false,\"message\":\"Nao foi possivel avancar por fast-forward (historico local divergente de origin/${BRANCH}). Nada foi alterado. Saida: ${SAIDA_MERGE//\"/\\\"}\"}"
  exit 1
fi

SAIDA_SYNC=$(bash "$SYNC_SCRIPTS" 2>&1)
if [ $? -ne 0 ]; then
  echo "{\"success\":false,\"message\":\"Codigo atualizado, mas falhou ao sincronizar scripts de sistema: ${SAIDA_SYNC//\"/\\\"}\"}"
  exit 1
fi

LOCK_DEPOIS=""
[ -f composer.lock ] && LOCK_DEPOIS="$(sha1sum composer.lock)"

if [ -f composer.lock ] && [ "$LOCK_ANTES" != "$LOCK_DEPOIS" ] && command -v composer >/dev/null 2>&1; then
  SAIDA_COMPOSER=$(sudo -u "$REPO_USER" composer install --no-dev --optimize-autoloader --no-interaction --quiet 2>&1)
  if [ $? -ne 0 ]; then
    echo "{\"success\":false,\"message\":\"Codigo atualizado, mas falhou ao instalar dependencias do composer: ${SAIDA_COMPOSER//\"/\\\"}\"}"
    exit 1
  fi
fi

echo '{"success":true,"message":"Atualizacao aplicada com sucesso."}'
