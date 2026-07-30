#!/bin/bash
# backup_aplicar_config_web.sh <arquivo_tmp>
#
# Instala o conteudo de <arquivo_tmp> em /etc/rd-intranet/rclone/rclone.conf,
# o unico arquivo que o modulo Backup gerencia (regenerado do zero a cada
# criacao/edicao/exclusao de destino, ver BackupService::aplicarConfig()).
# <arquivo_tmp> e gerado pelo PHP via tempnam() -- mesmo esquema ja usado
# por CronService::regenerarArquivo()/cron_aplicar_web.sh -- e contem os
# segredos (chaves de API) em texto puro, por isso o destino final fica
# 600 (so root le), diferente do cron.d que e 644.

set -u

ORIGEM="$1"
DESTINO_DIR="/etc/rd-intranet/rclone"
DESTINO="${DESTINO_DIR}/rclone.conf"

if [ ! -f "$ORIGEM" ]; then
  echo '{"success":false,"message":"Arquivo de origem nao encontrado."}'
  exit 1
fi

mkdir -p "$DESTINO_DIR"
chown root:root "$DESTINO_DIR"
chmod 700 "$DESTINO_DIR"

cp "$ORIGEM" "$DESTINO"
chown root:root "$DESTINO"
chmod 600 "$DESTINO"

echo '{"success":true,"message":"Configuracao de backup atualizada com sucesso."}'
