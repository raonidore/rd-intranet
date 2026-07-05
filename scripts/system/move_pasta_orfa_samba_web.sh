#!/bin/bash

NOME="$1"

if [[ ! "$NOME" =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "Nome inválido"
  exit 1
fi

ORIGEM="/srv/samba/Compartilhamentos/$NOME"
DESTINO_BASE="/srv/samba/.deleted"
DESTINO="$DESTINO_BASE/${NOME}_$(date +%Y%m%d%H%M%S)"

if [ ! -d "$ORIGEM" ]; then
  echo "Pasta não encontrada"
  exit 1
fi

mkdir -p "$DESTINO_BASE"
mv "$ORIGEM" "$DESTINO"

echo "OK"
echo "Pasta movida para: $DESTINO"
