#!/bin/bash
NOME="$1"

if [[ ! "$NOME" =~ ^[A-Za-z0-9_-]+_[0-9]{14}$ ]]; then
  echo "Nome inválido"
  exit 1
fi

ALVO="/srv/samba/.deleted/$NOME"

if [ ! -d "$ALVO" ]; then
  echo "Item não encontrado"
  exit 1
fi

rm -rf "$ALVO"

echo "OK"
echo "Item removido definitivamente"
