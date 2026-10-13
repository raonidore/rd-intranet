#!/bin/bash
# wifi_portal_apache_web.sh ativar|desativar
#
# Publica o portal do Wi-Fi em /guest (o UniFi manda o aparelho para
# http://<ip>/guest/s/<site>/...). É um Alias para public/guest, que tem
# front controller próprio, separado do painel em /rd.intranet.

set -u

ACAO="${1:-}"
REPO_DIR="/var/www/rd.intranet"
CONF="/etc/apache2/conf-available/rd-intranet-wifi.conf"

json() { python3 -c 'import json,sys; print(json.dumps({"success": sys.argv[1] == "1", "message": sys.argv[2]}))' "$1" "$2"; }

case "$ACAO" in
  ativar)
    cat > "$CONF" <<EOF
# Gerado por wifi_portal_apache_web.sh -- portal do Wi-Fi.
Alias /guest ${REPO_DIR}/public/guest
<Directory ${REPO_DIR}/public/guest>
    AllowOverride All
    Require all granted
</Directory>
EOF
    a2enmod rewrite >/dev/null 2>&1
    a2enconf rd-intranet-wifi >/dev/null 2>&1
    ;;
  desativar)
    a2disconf rd-intranet-wifi >/dev/null 2>&1
    ;;
  *) json 0 "Ação inválida."; exit 1 ;;
esac

if ! apache2ctl configtest >/dev/null 2>&1; then
  a2disconf rd-intranet-wifi >/dev/null 2>&1
  json 0 "Configuração do Apache inválida: $(apache2ctl configtest 2>&1 | tail -3 | tr '\n' ' ')"
  exit 1
fi
systemctl reload apache2
json 1 "Portal $( [ "$ACAO" = ativar ] && echo publicado em /guest || echo retirado do ar )."
