#!/bin/bash
# certificado_ativar_web.sh
# Garante mod_ssl habilitado e o vhost :443 ativo, apontando pro certificado
# em /etc/ssl/rd-intranet/atual.{crt,key}. Idempotente -- chamado depois de
# qualquer troca de certificado (gerar/importar/Let's Encrypt). So mexe no
# vhost dedicado (rd.intranet-ssl.conf); nunca toca no vhost :80 existente,
# entao o HTTP continua de pe mesmo se isto falhar.

set -u

if [ ! -f /etc/ssl/rd-intranet/atual.crt ] || [ ! -f /etc/ssl/rd-intranet/atual.key ]; then
  echo '{"success":false,"message":"Nenhum certificado disponivel em /etc/ssl/rd-intranet/."}'
  exit 1
fi

mkdir -p /etc/ssl/rd-intranet

# Grupo "ssl-cert" (convencao padrao Debian/Ubuntu) le a chave privada sem
# precisar rodar como root -- usado pela ponte RDP pelo navegador
# (guacamole_bridge_instalar_web.sh), que reaproveita este mesmo
# certificado do Apache pra terminar TLS. So concede leitura de grupo,
# nunca afrouxa "outros" nem muda o dono real (continua root). O grupo
# "ssl-cert" existe por padrao no Ubuntu/Debian (pacote base), nao
# precisa ser criado aqui.
chgrp ssl-cert /etc/ssl/rd-intranet 2>/dev/null || true
chmod 750 /etc/ssl/rd-intranet
chown root:root /etc/ssl/rd-intranet/atual.crt
chown root:ssl-cert /etc/ssl/rd-intranet/atual.key
chmod 644 /etc/ssl/rd-intranet/atual.crt
chmod 640 /etc/ssl/rd-intranet/atual.key

VHOST="/etc/apache2/sites-available/rd.intranet-ssl.conf"
if [ ! -f "$VHOST" ]; then
  cat > "$VHOST" <<'EOF'
<VirtualHost *:443>
    ServerName rd.intranet

    DocumentRoot /var/www/rd.intranet/public

    <Directory /var/www/rd.intranet/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile /etc/ssl/rd-intranet/atual.crt
    SSLCertificateKeyFile /etc/ssl/rd-intranet/atual.key

    ErrorLog ${APACHE_LOG_DIR}/rd.intranet_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/rd.intranet_ssl_access.log combined
</VirtualHost>
EOF
  chmod 644 "$VHOST"
fi

a2enmod ssl >/dev/null 2>&1
a2ensite rd.intranet-ssl >/dev/null 2>&1

if ! apache2ctl configtest >/dev/null 2>&1; then
  ERRO="$(apache2ctl configtest 2>&1 | tr '\n' ' ' | sed 's/"/\\"/g')"
  a2dissite rd.intranet-ssl >/dev/null 2>&1
  echo "{\"success\":false,\"message\":\"Configuracao invalida, HTTPS nao foi ativado (HTTP continua normal): ${ERRO}\"}"
  exit 1
fi

systemctl reload apache2

echo '{"success":true,"message":"HTTPS ativo em https://<host>/ (porta 443)."}'
