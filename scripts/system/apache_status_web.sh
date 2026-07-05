#!/bin/bash
# apache_status_web.sh — snapshot pra tela de Dashboard Apache (só leitura).

echo "### VERSAO"
apache2 -v 2>/dev/null | head -1 | sed 's/Server version: //'

echo ""
echo "### SERVICO"
echo "STATUS=$(systemctl is-active apache2)"
echo "ENABLED=$(systemctl is-enabled apache2 2>/dev/null || echo desconhecido)"

echo ""
echo "### CONFIGTEST"
if apache2ctl configtest >/dev/null 2>&1; then
  echo "OK"
else
  echo "ERRO"
fi

echo ""
echo "### SITES"
echo "DISPONIVEIS=$(ls /etc/apache2/sites-available/*.conf 2>/dev/null | wc -l)"
echo "HABILITADOS=$(ls /etc/apache2/sites-enabled/*.conf 2>/dev/null | wc -l)"

echo ""
echo "### MODULOS"
echo "DISPONIVEIS=$(ls /etc/apache2/mods-available/*.load 2>/dev/null | wc -l)"
echo "HABILITADOS=$(ls /etc/apache2/mods-enabled/*.load 2>/dev/null | wc -l)"

echo ""
echo "### SSL"
if apache2ctl -M 2>/dev/null | grep -q "ssl_module"; then
  echo "MODULO=sim"
else
  echo "MODULO=nao"
fi
if ss -ltn 2>/dev/null | grep -q ":443 "; then
  echo "PORTA_443=sim"
else
  echo "PORTA_443=nao"
fi

echo ""
echo "### LOGS"
for f in /var/log/apache2/*.log; do
  [ -f "$f" ] || continue
  echo "$(basename "$f")|$(stat -c '%s' "$f")"
done
