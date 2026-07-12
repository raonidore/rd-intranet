#!/bin/bash
# vpn_openvpn_aplicar_web.sh <tmp_path>
# Aplica um server.conf gerado pela tela (PHP escreve em
# /etc/openvpn/server/rd/tmp/, root:www-data 775). Nome do servidor e
# fixo ("server", igual ao certificado emitido pela PKI) -- unidade
# systemd e "openvpn-server@server".
#
# OpenVPN nao tem um "testparm" equivalente pra validar a config sem
# subir de verdade -- por isso a validacao aqui e "tentar subir e
# conferir se ficou ativo", com timeout curto, e reverter o backup se
# nao subir.

set -u

TEMP_FILE="${1:-}"
NOME_SERVIDOR="server"
CONF_ATIVO="/etc/openvpn/server/${NOME_SERVIDOR}.conf"
BACKUP="/etc/openvpn/server/rd/backups/${NOME_SERVIDOR}_$(date +%Y%m%d%H%M%S).conf"
UNIDADE="openvpn-server@${NOME_SERVIDOR}"

if [ -z "$TEMP_FILE" ] || [ ! -f "$TEMP_FILE" ]; then
  echo '{"success":false,"message":"Arquivo temporário não encontrado."}'
  exit 1
fi

if [ -f "$CONF_ATIVO" ]; then
  cp "$CONF_ATIVO" "$BACKUP"
fi

cp "$TEMP_FILE" "$CONF_ATIVO"
chmod 600 "$CONF_ATIVO"

systemctl enable "$UNIDADE" >/dev/null 2>&1
systemctl restart "$UNIDADE"

sleep 2

if systemctl is-active --quiet "$UNIDADE"; then
  echo '{"success":true,"message":"Configuração aplicada e serviço ativo."}'
  exit 0
fi

ERRO="$(journalctl -u "$UNIDADE" -n 15 --no-pager 2>/dev/null | tr '\n' ' ' | sed 's/"/\\"/g')"

if [ -f "$BACKUP" ]; then
  cp "$BACKUP" "$CONF_ATIVO"
  systemctl restart "$UNIDADE" >/dev/null 2>&1
fi

echo "{\"success\":false,\"message\":\"Falha ao subir o serviço, configuração anterior restaurada: ${ERRO}\"}"
exit 1
