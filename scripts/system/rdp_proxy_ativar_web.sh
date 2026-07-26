#!/bin/bash
# rdp_proxy_ativar_web.sh
# Garante o proxy reverso WebSocket (Apache -> ponte RDP em 127.0.0.1)
# ativo no vhost HTTPS -- assim o "RDP pelo navegador" passa pela MESMA
# porta que o site já usa (443), sem precisar liberar porta nenhuma a
# mais no roteador/NAT de cada servidor. Idempotente -- chamado sempre
# que "Preparar suporte a RDP no navegador" roda, nunca mexe no vhost :80
# nem em mais nada do site.

set -u

VHOST="/etc/apache2/sites-available/rd.intranet-ssl.conf"
MARCA="# RD Intranet - proxy da ponte RDP pelo navegador"

if [ ! -f "$VHOST" ]; then
  echo '{"success":false,"message":"Nenhum certificado HTTPS ativo -- configure em Infraestrutura > Certificado Digital antes de ativar RDP pelo navegador (o proxy precisa do vhost :443 já existir)."}'
  exit 1
fi

a2enmod proxy >/dev/null 2>&1
a2enmod proxy_wstunnel >/dev/null 2>&1

if ! grep -qF "$MARCA" "$VHOST"; then
  TMP="$(mktemp)"
  awk -v marca="$MARCA" '
    /<\/VirtualHost>/ && !inserido {
      print "    " marca
      print "    ProxyPass \"/rdp-ws\" \"ws://127.0.0.1:8092/\""
      print "    ProxyPassReverse \"/rdp-ws\" \"ws://127.0.0.1:8092/\""
      inserido = 1
    }
    { print }
  ' "$VHOST" > "$TMP"

  if ! grep -qF "$MARCA" "$TMP"; then
    rm -f "$TMP"
    echo '{"success":false,"message":"Não encontrei </VirtualHost> em rd.intranet-ssl.conf pra inserir o proxy -- vhost em formato inesperado, confira manualmente."}'
    exit 1
  fi

  cp "$TMP" "$VHOST"
  rm -f "$TMP"
  chmod 644 "$VHOST"
fi

if ! apache2ctl configtest >/dev/null 2>&1; then
  ERRO="$(apache2ctl configtest 2>&1 | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"Configuração do Apache inválida depois de adicionar o proxy do RDP -- nada foi recarregado. Saída: ${ERRO}\"}"
  exit 1
fi

systemctl reload apache2

echo '{"success":true,"message":"Proxy do RDP pelo navegador ativo em /rdp-ws (mesma porta HTTPS do site)."}'
