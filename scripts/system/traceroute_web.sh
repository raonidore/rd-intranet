#!/bin/bash
# traceroute_web.sh <destino>
# Mesma validacao estrita do ping_web.sh.

DESTINO="$1"

RE_HOST='^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$'
RE_IPV4='^[0-9]{1,3}(\.[0-9]{1,3}){3}$'
RE_IPV6='^[0-9A-Fa-f:]+$'

if [[ ! "$DESTINO" =~ $RE_HOST ]] && [[ ! "$DESTINO" =~ $RE_IPV4 ]] && [[ ! "$DESTINO" =~ $RE_IPV6 ]]; then
  echo "Destino invalido"
  exit 1
fi

if command -v traceroute >/dev/null 2>&1; then
  timeout 20 traceroute -m 15 -q 1 -w 2 -- "$DESTINO" 2>&1
else
  timeout 20 tracepath -m 15 -- "$DESTINO" 2>&1
fi
