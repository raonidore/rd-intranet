#!/bin/bash

NOME="$1"
CAMINHO="$2"
GRUPO="$3"

if [[ ! "$NOME" =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "Nome inválido"
  exit 1
fi

if [[ ! "$GRUPO" =~ ^[a-z0-9_-]+$ ]]; then
  echo "Grupo inválido"
  exit 1
fi

if [[ "$CAMINHO" != /srv/samba/Compartilhamentos/* ]]; then
  echo "Caminho inválido. Use /srv/samba/Compartilhamentos/"
  exit 1
fi

if ! getent group "$GRUPO" >/dev/null; then
  groupadd "$GRUPO"
fi

mkdir -p "$CAMINHO"

chown root:"$GRUPO" "$CAMINHO"
chmod 2770 "$CAMINHO"

echo "OK"
