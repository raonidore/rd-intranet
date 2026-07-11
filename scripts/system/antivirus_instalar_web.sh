#!/bin/bash
# antivirus_instalar_web.sh
# Instala o ClamAV (daemon + atualizador de assinaturas) e sobe os
# serviços. A primeira atualização de assinaturas (feita pelo proprio
# clamav-freshclam.service em segundo plano) pode levar alguns minutos --
# clamd so fica realmente pronto depois disso.

set -u

export DEBIAN_FRONTEND=noninteractive

if ! apt-get update -qq 2>/tmp/rd_av_err_$$; then
  ERRO="$(tail -10 /tmp/rd_av_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_av_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao atualizar lista de pacotes: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_av_err_$$

if ! apt-get install -y -qq clamav-daemon clamav-freshclam 2>/tmp/rd_av_err_$$; then
  ERRO="$(tail -20 /tmp/rd_av_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_av_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar ClamAV: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_av_err_$$

mkdir -p /var/quarantine/rd-intranet
chmod 700 /var/quarantine/rd-intranet

systemctl enable --now clamav-freshclam >/dev/null 2>&1 || true
systemctl enable --now clamav-daemon >/dev/null 2>&1 || true

echo '{"success":true,"message":"ClamAV instalado. As assinaturas de vírus baixam em segundo plano -- pode levar alguns minutos até o serviço ficar pronto para escanear."}'
