#!/bin/bash
# certificado_gerar_autoassinado_web.sh <nome_comum> [ip_adicional]
#
# Gera um certificado autoassinado (openssl, RSA 2048, valido 825 dias -- o
# maximo aceito pela maioria dos navegadores) cobrindo o hostname informado
# e, se dado, um IP adicional via Subject Alternative Name. Faz backup do
# certificado anterior antes de sobrescrever.

set -u

CN="$1"
IP_EXTRA="${2:-}"

if ! [[ "$CN" =~ ^[a-zA-Z0-9.-]+$ ]]; then
  echo '{"success":false,"message":"Nome (CN) invalido."}'
  exit 1
fi
if [ -n "$IP_EXTRA" ] && ! [[ "$IP_EXTRA" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]]; then
  echo '{"success":false,"message":"IP adicional invalido."}'
  exit 1
fi

mkdir -p /etc/ssl/rd-intranet /etc/rd-intranet/.certificado-backups
chmod 750 /etc/ssl/rd-intranet

TS="$(date +%Y%m%d%H%M%S%N)"
BACKUP_CRT=""
BACKUP_KEY=""
if [ -f /etc/ssl/rd-intranet/atual.crt ]; then
  BACKUP_CRT="/etc/rd-intranet/.certificado-backups/atual.crt.bkp.$TS"
  BACKUP_KEY="/etc/rd-intranet/.certificado-backups/atual.key.bkp.$TS"
  cp /etc/ssl/rd-intranet/atual.crt "$BACKUP_CRT"
  cp /etc/ssl/rd-intranet/atual.key "$BACKUP_KEY" 2>/dev/null
fi

SAN="DNS:${CN}"
[ -n "$IP_EXTRA" ] && SAN="${SAN},IP:${IP_EXTRA}"

if ! openssl req -x509 -nodes -newkey rsa:2048 \
    -keyout /etc/ssl/rd-intranet/atual.key.tmp \
    -out /etc/ssl/rd-intranet/atual.crt.tmp \
    -days 825 \
    -subj "/CN=${CN}" \
    -addext "subjectAltName=${SAN}" 2>/tmp/rd_cert_err_$$; then
  ERRO="$(cat /tmp/rd_cert_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_cert_err_$$ /etc/ssl/rd-intranet/atual.key.tmp /etc/ssl/rd-intranet/atual.crt.tmp
  echo "{\"success\":false,\"message\":\"Erro ao gerar certificado: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_cert_err_$$

mv /etc/ssl/rd-intranet/atual.crt.tmp /etc/ssl/rd-intranet/atual.crt
mv /etc/ssl/rd-intranet/atual.key.tmp /etc/ssl/rd-intranet/atual.key
chmod 644 /etc/ssl/rd-intranet/atual.crt
chmod 600 /etc/ssl/rd-intranet/atual.key

mkdir -p /etc/rd-intranet
echo "autoassinado" > /etc/rd-intranet/.certificado-tipo
echo "$CN" > /etc/rd-intranet/.certificado-dominio

echo "{\"success\":true,\"message\":\"Certificado autoassinado gerado para ${CN}.\",\"backup_crt\":\"${BACKUP_CRT}\",\"backup_key\":\"${BACKUP_KEY}\"}"
