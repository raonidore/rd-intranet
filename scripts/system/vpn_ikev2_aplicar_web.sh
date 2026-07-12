#!/bin/bash
# vpn_ikev2_aplicar_web.sh
# Aplica ipsec.conf + ipsec.secrets gerados pela tela (PHP escreve em
# /etc/rd-intranet/ikev2/tmp/, root:www-data 775). Os dois arquivos
# sempre juntos porque um "conn" sem segredo correspondente (ou
# vice-versa) deixa o strongSwan num estado inconsistente.
#
# strongSwan nao tem um "testparm" equivalente -- validacao e "tentar
# reiniciar e conferir se o daemon volta saudavel" (igual ao OpenVPN).

set -u

TMP_CONF="/etc/rd-intranet/ikev2/tmp/ipsec.conf.tmp"
TMP_SECRETS="/etc/rd-intranet/ikev2/tmp/ipsec.secrets.tmp"
TMP_CACERTS="/etc/rd-intranet/ikev2/tmp/cacerts"
CACERTS_DIR="/etc/rd-intranet/ikev2/cacerts"

if [ ! -f "$TMP_CONF" ] || [ ! -f "$TMP_SECRETS" ]; then
  echo '{"success":false,"message":"Arquivos temporários não encontrados."}'
  exit 1
fi

mkdir -p "$CACERTS_DIR"
rm -f "${CACERTS_DIR:?}"/*.pem
if [ -d "$TMP_CACERTS" ]; then
  cp "$TMP_CACERTS"/*.pem "$CACERTS_DIR"/ 2>/dev/null || true
fi

TS="$(date +%Y%m%d%H%M%S)"
BACKUP_CONF="/etc/rd-intranet/ikev2/backups/ipsec.conf_${TS}"
BACKUP_SECRETS="/etc/rd-intranet/ikev2/backups/ipsec.secrets_${TS}"

[ -f /etc/ipsec.conf ] && cp /etc/ipsec.conf "$BACKUP_CONF"
[ -f /etc/ipsec.secrets ] && cp /etc/ipsec.secrets "$BACKUP_SECRETS"

cp "$TMP_CONF" /etc/ipsec.conf
cp "$TMP_SECRETS" /etc/ipsec.secrets
chmod 600 /etc/ipsec.secrets
chmod 644 /etc/ipsec.conf

ipsec restart >/tmp/rd_ikev2_apl_err_$$ 2>&1
sleep 2

if ipsec statusall 2>/dev/null | grep -q "Status of IKE charon daemon"; then
  rm -f /tmp/rd_ikev2_apl_err_$$
  echo '{"success":true,"message":"Configuração aplicada e o daemon está ativo."}'
  exit 0
fi

ERRO="$(tail -15 /tmp/rd_ikev2_apl_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
rm -f /tmp/rd_ikev2_apl_err_$$

if [ -f "$BACKUP_CONF" ]; then
  cp "$BACKUP_CONF" /etc/ipsec.conf
fi
if [ -f "$BACKUP_SECRETS" ]; then
  cp "$BACKUP_SECRETS" /etc/ipsec.secrets
fi
ipsec restart >/dev/null 2>&1

echo "{\"success\":false,\"message\":\"Falha ao aplicar, configuração anterior restaurada: ${ERRO}\"}"
exit 1
