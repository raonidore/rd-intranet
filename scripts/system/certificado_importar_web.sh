#!/bin/bash
# certificado_importar_web.sh <arquivo_crt_tmp> <arquivo_key_tmp> [arquivo_chain_tmp]
#
# Instala um certificado proprio (comprado ou de CA corporativa) enviado
# pela tela. Valida que o certificado e a chave batem (mesmo modulus) antes
# de instalar, e faz backup do certificado anterior.

set -u

CRT_TMP="$1"
KEY_TMP="$2"
CHAIN_TMP="${3:-}"

for F in "$CRT_TMP" "$KEY_TMP"; do
  if [ ! -f "$F" ]; then
    echo '{"success":false,"message":"Arquivo enviado nao encontrado."}'
    exit 1
  fi
done

RAW_CRT="$(openssl x509 -noout -modulus -in "$CRT_TMP" 2>/dev/null)"
RAW_KEY="$(openssl rsa -noout -modulus -in "$KEY_TMP" 2>/dev/null)"

# checa vazio ANTES de passar pelo md5 -- md5 de entrada vazia ainda produz
# um hash valido (nao vazio), entao checar so o resultado final do pipe
# nunca pegaria um arquivo invalido aqui
if [ -z "$RAW_CRT" ]; then
  echo '{"success":false,"message":"Arquivo de certificado invalido (nao e um .crt/.pem valido)."}'
  exit 1
fi
if [ -z "$RAW_KEY" ]; then
  echo '{"success":false,"message":"Arquivo de chave privada invalido (nao e uma .key valida, ou esta protegida por senha)."}'
  exit 1
fi

MOD_CRT="$(echo "$RAW_CRT" | openssl md5)"
MOD_KEY="$(echo "$RAW_KEY" | openssl md5)"
if [ "$MOD_CRT" != "$MOD_KEY" ]; then
  echo '{"success":false,"message":"O certificado e a chave privada nao correspondem entre si."}'
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

cp "$CRT_TMP" /etc/ssl/rd-intranet/atual.crt
if [ -n "$CHAIN_TMP" ] && [ -f "$CHAIN_TMP" ]; then
  cat "$CHAIN_TMP" >> /etc/ssl/rd-intranet/atual.crt
fi
cp "$KEY_TMP" /etc/ssl/rd-intranet/atual.key
chmod 644 /etc/ssl/rd-intranet/atual.crt
chmod 600 /etc/ssl/rd-intranet/atual.key

CN="$(openssl x509 -noout -subject -in /etc/ssl/rd-intranet/atual.crt 2>/dev/null | sed -n 's/.*CN\s*=\s*\([^,\/]*\).*/\1/p')"

mkdir -p /etc/rd-intranet
echo "importado" > /etc/rd-intranet/.certificado-tipo
echo "${CN:-desconhecido}" > /etc/rd-intranet/.certificado-dominio

echo "{\"success\":true,\"message\":\"Certificado importado com sucesso.\",\"backup_crt\":\"${BACKUP_CRT}\",\"backup_key\":\"${BACKUP_KEY}\"}"
