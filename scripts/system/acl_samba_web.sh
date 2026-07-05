#!/bin/bash

BASE="/srv/samba/Compartilhamentos"

find "$BASE" -mindepth 1 -maxdepth 1 -type d | while read PASTA; do
  NOME=$(basename "$PASTA")

  echo "### SHARE:$NOME"
  echo "PATH=$PASTA"
  getfacl -p "$PASTA" 2>/dev/null
  echo ""
done
