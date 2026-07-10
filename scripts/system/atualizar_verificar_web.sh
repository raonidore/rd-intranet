#!/bin/bash
# atualizar_verificar_web.sh
#
# So atualiza as refs remotas do git (fetch), sem tocar na working tree.
# O fetch grava em .git/objects, que pertence ao dono do checkout -- por
# isso roda via 'sudo -u', mesmo este script ja rodando como root (chamado
# via sudo a partir do www-data, ver LinuxService::executarScript). A
# leitura de HEAD/origin/log de commits pendentes fica por conta do PHP
# direto (AtualizacaoService), sem sudo, ja que www-data consegue ler o
# .git normalmente.

set -u

REPO_DIR="/var/www/rd.intranet"
REPO_USER="ti"
BRANCH="main"

cd "$REPO_DIR" || { echo '{"success":false,"message":"Diretorio do repositorio nao encontrado."}'; exit 1; }

SAIDA=$(sudo -u "$REPO_USER" git fetch origin "$BRANCH" --quiet 2>&1)
if [ $? -ne 0 ]; then
  echo "{\"success\":false,\"message\":\"Erro ao buscar atualizacoes: ${SAIDA//\"/\\\"}\"}"
  exit 1
fi

echo "{\"success\":true,\"message\":\"origin/${BRANCH} atualizado.\"}"
