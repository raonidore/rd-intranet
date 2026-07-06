#!/bin/bash
# ping_web.sh <destino>
# Destino validado por regex (hostname RFC 1123, IPv4 ou IPv6 literal) antes
# de tocar no shell -- nunca aceita nada fora desse formato.

DESTINO="$1"

RE_HOST='^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$'
RE_IPV4='^[0-9]{1,3}(\.[0-9]{1,3}){3}$'
RE_IPV6='^[0-9A-Fa-f:]+$'

if [[ ! "$DESTINO" =~ $RE_HOST ]] && [[ ! "$DESTINO" =~ $RE_IPV4 ]] && [[ ! "$DESTINO" =~ $RE_IPV6 ]]; then
  echo "Destino invalido"
  exit 1
fi

ping -c 4 -W 2 -- "$DESTINO" 2>&1
