#!/bin/bash
# setup_php_upload_limits.sh
# Passo de instalacao/atualizacao (rodar uma vez, como root). NAO tem
# sufixo _web.sh de proposito -- roda dentro de atualizar_aplicar_web.sh
# (que ja executa como root via sudoers) e tambem e chamado direto pelo
# install.sh, mesmo criterio dos outros setup_*.sh.
#
# Os padroes do PHP (upload_max_filesize=2M, post_max_size=8M) sao
# pequenos demais pros uploads que a RD Intranet ja oferece: instalador
# do .NET Desktop Runtime (~60MB), agente .exe self-contained (ate
# ~100MB), upload de arquivo do Samba (ja assume ate 100MB no proprio
# codigo, SambaArquivosController::MAX_UPLOAD). Quando post_max_size e
# excedido o PHP nem populacao $_FILES -- o upload falha silenciosamente
# (sem UPLOAD_ERR_* nenhum), o que e dificil de diagnosticar a distancia.
# Nao da pra ajustar isso via .htaccess (upload_max_filesize/post_max_size
# sao PHP_INI_PERDIR, so em php.ini/conf.d), por isso um arquivo proprio
# em conf.d em vez de editar o php.ini principal direto -- mais facil de
# versionar a intencao e não conflita se o admin editar o php.ini a mao.
# Idempotente: sobrescreve o mesmo arquivo toda vez.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

VERSAO_PHP=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
PASTA_CONFD="/etc/php/${VERSAO_PHP}/apache2/conf.d"

if [ ! -d "$PASTA_CONFD" ]; then
  echo "Nao achei $PASTA_CONFD -- libapache2-mod-php nao parece instalado." >&2
  exit 1
fi

cat > "$PASTA_CONFD/99-rd-intranet-uploads.ini" <<'EOF'
; Gerado por scripts/system/setup_php_upload_limits.sh -- nao edite a
; mao, esse arquivo e sobrescrito a cada instalacao/atualizacao.
upload_max_filesize = 200M
post_max_size = 210M
max_input_time = 300
EOF

systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2

echo "OK"
