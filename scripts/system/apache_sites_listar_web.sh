#!/bin/bash
# apache_sites_listar_web.sh — lista sites-available com estado e docroot.

for f in /etc/apache2/sites-available/*.conf; do
  [ -f "$f" ] || continue
  nome=$(basename "$f")
  if [ -e "/etc/apache2/sites-enabled/$nome" ]; then
    estado="habilitado"
  else
    estado="desabilitado"
  fi
  docroot=$(grep -m1 -i "^\s*DocumentRoot" "$f" | awk '{print $2}')
  servername=$(grep -m1 -i "^\s*ServerName" "$f" | awk '{print $2}')
  echo "${nome}|${estado}|${docroot:--}|${servername:--}"
done | sort
