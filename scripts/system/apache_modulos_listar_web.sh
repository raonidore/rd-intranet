#!/bin/bash
# apache_modulos_listar_web.sh — lista mods-available com estado (habilitado/nao).

for f in /etc/apache2/mods-available/*.load; do
  [ -f "$f" ] || continue
  nome=$(basename "$f" .load)
  if [ -e "/etc/apache2/mods-enabled/$(basename "$f")" ]; then
    estado="habilitado"
  else
    estado="desabilitado"
  fi
  echo "${nome}|${estado}"
done | sort
