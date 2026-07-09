#!/bin/bash
# certificado_letsencrypt_web.sh <dominio> <email>
#
# Obtem (ou renova) um certificado Let's Encrypt via certbot, modo webroot
# (precisa da porta 80 alcancavel pela internet com o dominio resolvendo
# pra este servidor). Copia pros caminhos padrao (atual.crt/atual.key) e
# configura um deploy-hook do certbot pra manter isso atualizado a cada
# renovacao automatica.

set -u

DOMINIO="$1"
EMAIL="$2"

if ! [[ "$DOMINIO" =~ ^[a-zA-Z0-9.-]+$ ]]; then
  echo '{"success":false,"message":"Dominio invalido."}'
  exit 1
fi
if ! [[ "$EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; then
  echo '{"success":false,"message":"E-mail invalido."}'
  exit 1
fi

if ! command -v certbot >/dev/null 2>&1; then
  echo '{"success":false,"message":"certbot nao esta instalado. Instale pelo checklist de dependencias (Infraestrutura > Dependencias) antes de continuar."}'
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

if ! certbot certonly --webroot -w /var/www/rd.intranet/public -d "$DOMINIO" \
    --non-interactive --agree-tos -m "$EMAIL" --no-eff-email 2>/tmp/rd_le_err_$$; then
  ERRO="$(tail -20 /tmp/rd_le_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_le_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao obter certificado Let's Encrypt: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_le_err_$$

LIVE="/etc/letsencrypt/live/${DOMINIO}"
if [ ! -f "$LIVE/fullchain.pem" ] || [ ! -f "$LIVE/privkey.pem" ]; then
  echo '{"success":false,"message":"Certbot rodou mas os arquivos esperados nao foram encontrados."}'
  exit 1
fi

cp "$LIVE/fullchain.pem" /etc/ssl/rd-intranet/atual.crt
cp "$LIVE/privkey.pem" /etc/ssl/rd-intranet/atual.key
chmod 644 /etc/ssl/rd-intranet/atual.crt
chmod 600 /etc/ssl/rd-intranet/atual.key

mkdir -p /etc/letsencrypt/renewal-hooks/deploy
cat > /etc/letsencrypt/renewal-hooks/deploy/rd-intranet.sh <<EOF
#!/bin/bash
cp "$LIVE/fullchain.pem" /etc/ssl/rd-intranet/atual.crt
cp "$LIVE/privkey.pem" /etc/ssl/rd-intranet/atual.key
chmod 644 /etc/ssl/rd-intranet/atual.crt
chmod 600 /etc/ssl/rd-intranet/atual.key
systemctl reload apache2
EOF
chmod +x /etc/letsencrypt/renewal-hooks/deploy/rd-intranet.sh

mkdir -p /etc/rd-intranet
echo "letsencrypt" > /etc/rd-intranet/.certificado-tipo
echo "$DOMINIO" > /etc/rd-intranet/.certificado-dominio

echo "{\"success\":true,\"message\":\"Certificado Let's Encrypt obtido para ${DOMINIO}. Renovacao automatica configurada.\",\"backup_crt\":\"${BACKUP_CRT}\",\"backup_key\":\"${BACKUP_KEY}\"}"
