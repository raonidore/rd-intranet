#!/bin/bash
# setup_storage_uploads.sh
# Passo de instalacao/atualizacao (rodar uma vez, como root). NAO tem
# sufixo _web.sh de proposito -- roda dentro de atualizar_aplicar_web.sh
# (que ja executa como root via sudoers) e tambem e chamado direto pelo
# install.sh, mesmo criterio dos outros setup_*.sh.
#
# storage/uploads, storage/cache e storage/logs sao .gitignore'd (nao vem
# do git clone/pull) e ficam de fora do "REPO_USER grava, www-data so le"
# que o resto do checkout usa -- essas 3 pastas sao escritas em tempo de
# execucao pela propria aplicacao (upload de arquivo, cache, log), entao
# precisam ser www-data. Sem isso, qualquer feature de upload falha com
# "Falha ao criar a pasta de destino no servidor" -- foi o que aconteceu
# com o upload do .exe do agente em Ativos > Dashboard num servidor novo.
# Idempotente: reaplicado a cada atualizacao, cobre tanto quem instalou
# antes desse ajuste existir quanto qualquer feature futura que passe a
# gravar em storage/uploads.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

REPO_DIR="${1:-/var/www/rd.intranet}"

mkdir -p "$REPO_DIR/storage/uploads" "$REPO_DIR/storage/cache" "$REPO_DIR/storage/logs"
chown -R www-data:www-data "$REPO_DIR/storage/uploads" "$REPO_DIR/storage/cache" "$REPO_DIR/storage/logs"

echo "OK"
