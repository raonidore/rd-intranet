#!/bin/bash
# setup_storage_uploads.sh
# Passo de instalacao/atualizacao (rodar uma vez, como root). NAO tem
# sufixo _web.sh de proposito -- roda dentro de atualizar_aplicar_web.sh
# (que ja executa como root via sudoers) e tambem e chamado direto pelo
# install.sh, mesmo criterio dos outros setup_*.sh.
#
# Essas pastas sao .gitignore'd (nao vem do git clone/pull) e ficam de
# fora do "REPO_USER grava, www-data so le" que o resto do checkout usa
# -- sao escritas em tempo de execucao pela propria aplicacao (upload de
# anexo, cache, log), entao precisam ser www-data. O codigo que grava
# nelas (ex: ChamadoAnexoService, ContratoService, WhatsAppMidiaService)
# so faz `mkdir()` na hora do primeiro upload, sem checar o retorno --
# se a pasta "storage/" em si pertencer a outro usuario (ex: o usuario
# de deploy, "ti", dono do checkout do git) e www-data nao tiver
# permissao de escrita nela, esse mkdir() falha CALADO e a feature quebra
# com "Falha ao salvar o arquivo", sem nada no log que aponte pra causa.
# Ja aconteceu em mais de um servidor (upload do .exe do agente em Ativos
# > Dashboard; anexo de chamado) -- por isso toda pasta que algum Service
# cria sozinho em tempo de execucao mora aqui, criada e com dono corrigido
# de forma centralizada, em vez de depender do mkdir() de cada feature
# individualmente. Idempotente: reaplicado a cada atualizacao, cobre
# tanto quem instalou antes desse ajuste existir quanto qualquer feature
# futura que passe a gravar em storage/.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

REPO_DIR="${1:-/var/www/rd.intranet}"

PASTAS=(
  uploads cache logs
  chamados chamados_externos chat contratos documentos dvr_snapshots projetos whatsapp
)

for PASTA in "${PASTAS[@]}"; do
  mkdir -p "$REPO_DIR/storage/$PASTA"
done

chown -R www-data:www-data "${PASTAS[@]/#/$REPO_DIR/storage/}"

echo "OK"
