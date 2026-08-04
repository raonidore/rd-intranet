#!/bin/bash
# setup_rclone.sh
# Passo de instalacao (rodar uma vez, como root: sudo ./setup_rclone.sh).
# NAO tem sufixo _web.sh de proposito -- fora da regra de sudo NOPASSWD do
# www-data, mesmo criterio ja usado em setup_db_secret_key.sh.
#
# Instala o binario rclone (motor unico usado pelo modulo Backup pra
# espelhar os compartilhamentos do Samba em B2/S3/Google Drive) via apt --
# nunca "curl | bash" (mesmo criterio ja documentado em cloudflared_web.sh:
# nenhum script deste repo instala as cegas via pipe, e apt evita depender
# da rede externa pra rclone.org, que pode estar lenta/bloqueada dependendo
# do servidor do cliente). A versao do repositorio do Ubuntu/Debian e mais
# antiga que a "oficial" do site, mas os backends b2/s3/drive usados aqui
# sao estaveis ha anos, entao isso nao e um problema. Cria tambem o
# diretorio onde o rclone.conf (com os segredos dos destinos, ver
# BackupService::aplicarConfig()) vai morar -- root:root 700, so os
# scripts *_web.sh (via sudo) tem acesso.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

if ! command -v rclone >/dev/null 2>&1; then
  # "|| true": apt-get update retorna erro se QUALQUER repositorio de
  # terceiros configurado no servidor estiver quebrado (ex: o repo da
  # Ookla/speedtest-cli parou de funcionar de vez, "402 Payment Required",
  # depois que o packagecloud.io descontinuou a hospedagem gratuita) --
  # mesmo sem nenhuma relacao com rclone, esse setup roda em TODO
  # "Atualizar agora" (reaplicacao idempotente) e nao pode travar a
  # atualizacao do sistema inteiro por causa de um repositorio de terceiro
  # alheio. rclone vem do repositorio padrao do Ubuntu/Debian, que nao
  # depende do que quebrou -- segue best effort, o apt-get install abaixo
  # falha por conta propria (com erro proprio) se realmente nao tiver
  # como instalar.
  apt-get update -qq || true
  apt-get install -y -qq rclone
else
  echo "rclone ja instalado ($(rclone version | head -1))."
fi

mkdir -p /etc/rd-intranet/rclone
chown root:root /etc/rd-intranet/rclone
chmod 700 /etc/rd-intranet/rclone

echo "OK"
