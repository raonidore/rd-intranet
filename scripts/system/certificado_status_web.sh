#!/bin/bash
# certificado_status_web.sh
# Somente leitura: estado atual do HTTPS pra tela de Infraestrutura > Certificado Digital.

echo "=== MOD-SSL ==="
[ -e /etc/apache2/mods-enabled/ssl.load ] && echo "1" || echo "0"

echo "=== SITE-SSL ==="
[ -e /etc/apache2/sites-enabled/rd.intranet-ssl.conf ] && echo "1" || echo "0"

echo "=== CERT-ATUAL ==="
if [ -f /etc/ssl/rd-intranet/atual.crt ]; then
  openssl x509 -in /etc/ssl/rd-intranet/atual.crt -noout -subject -issuer -startdate -enddate -fingerprint -sha256 2>&1
else
  echo "NENHUM"
fi

echo "=== TIPO ==="
cat /etc/rd-intranet/.certificado-tipo 2>/dev/null || echo "nenhum"

echo "=== DOMINIO ==="
cat /etc/rd-intranet/.certificado-dominio 2>/dev/null || echo ""

echo "=== CERTBOT ==="
command -v certbot >/dev/null 2>&1 && echo "1" || echo "0"
