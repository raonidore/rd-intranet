#!/bin/bash
NOME="$1"

if [[ ! "$NOME" =~ ^[A-Za-z0-9_-]+_[0-9]{14}$ ]]; then
  echo "Nome inválido"
  exit 1
fi

ORIGEM="/srv/samba/.deleted/$NOME"
BASE="${NOME%_*}"
DESTINO="/srv/samba/Compartilhamentos/$BASE"

if [ ! -d "$ORIGEM" ]; then
  echo "Item não encontrado na lixeira"
  exit 1
fi

if [ -e "$DESTINO" ]; then
  echo "Já existe uma pasta com este nome em Compartilhamentos"
  exit 1
fi

mv "$ORIGEM" "$DESTINO"

echo "OK"
echo "Restaurado para: $DESTINO"
