#!/bin/bash
# mtr_web.sh <destino>
# Mesma validacao estrita do ping_web.sh/traceroute_web.sh.

DESTINO="$1"

RE_HOST='^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$'
RE_IPV4='^[0-9]{1,3}(\.[0-9]{1,3}){3}$'
RE_IPV6='^[0-9A-Fa-f:]+$'

if [[ ! "$DESTINO" =~ $RE_HOST ]] && [[ ! "$DESTINO" =~ $RE_IPV4 ]] && [[ ! "$DESTINO" =~ $RE_IPV6 ]]; then
  echo "Destino invalido"
  exit 1
fi

if command -v mtr >/dev/null 2>&1; then
  # -r relatorio (nao interativo), -w nao trunca hostname, -b mostra
  # host+IP, -c 20 ~20s de coleta -- suficiente pra flagrar perda
  # intermitente sem deixar a requisicao HTTP longa demais.
  timeout 45 mtr -r -w -b -c 20 -- "$DESTINO" 2>&1
else
  echo "mtr nao esta instalado. Instale o pacote 'mtr-tiny' em Infraestrutura > Dependencias."
  exit 1
fi
