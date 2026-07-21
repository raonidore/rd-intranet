#!/bin/bash
# agente_baixar_git.sh
#
# Busca o .exe do agente Windows ja compilado e publicado no repositorio
# git (agente-windows/dist/RdIntranetAgente.exe + VERSION.txt, na branch
# main), em vez de depender de upload manual pelo navegador -- util pra
# quem roda o RD Intranet em varios servidores e nao quer repetir o
# upload em cada um.
#
# So LEITURA do git (fetch + show), nunca mexe na working tree do
# checkout (nao da checkout/pull/merge/reset) -- "git show <ref>:<path>"
# le o conteudo direto do banco de objetos do git, sem tocar em nenhum
# arquivo do app que esta rodando ao vivo nesse servidor.

set -u

REPO_DIR="/var/www/rd.intranet"
REPO_USER="ti"
BRANCH="main"
DESTINO="$REPO_DIR/storage/uploads/agente/RdIntranetAgente.exe"

cd "$REPO_DIR" || { echo '{"success":false,"message":"Diretorio do repositorio nao encontrado."}'; exit 1; }

SAIDA_FETCH=$(sudo -u "$REPO_USER" git fetch origin "$BRANCH" --quiet 2>&1)
if [ $? -ne 0 ]; then
  echo "{\"success\":false,\"message\":\"Erro ao buscar atualizacoes do repositorio: ${SAIDA_FETCH//\"/\\\"}\"}"
  exit 1
fi

VERSAO=$(sudo -u "$REPO_USER" git show "origin/${BRANCH}:agente-windows/dist/VERSION.txt" 2>/dev/null | tr -d '[:space:]')
if [ -z "$VERSAO" ]; then
  echo '{"success":false,"message":"Nao encontrei agente-windows/dist/VERSION.txt no repositorio -- publique o .exe + VERSION.txt no git antes (veja o README do agente)."}'
  exit 1
fi

mkdir -p "$(dirname "$DESTINO")"
TEMP="${DESTINO}.baixando"

if ! sudo -u "$REPO_USER" git show "origin/${BRANCH}:agente-windows/dist/RdIntranetAgente.exe" > "$TEMP" 2>/dev/null; then
  rm -f "$TEMP"
  echo '{"success":false,"message":"Nao encontrei agente-windows/dist/RdIntranetAgente.exe no repositorio."}'
  exit 1
fi

if [ ! -s "$TEMP" ]; then
  rm -f "$TEMP"
  echo '{"success":false,"message":"O .exe do repositorio veio vazio -- confira se o build foi commitado corretamente."}'
  exit 1
fi

mv "$TEMP" "$DESTINO"
chown www-data:www-data "$DESTINO"
chmod 644 "$DESTINO"

echo "{\"success\":true,\"message\":\"Agente atualizado a partir do repositorio.\",\"versao\":\"${VERSAO}\"}"
