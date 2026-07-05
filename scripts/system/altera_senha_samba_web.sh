#!/bin/bash

LOGIN="$1"
SENHA="$2"

if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
  echo "Login inválido"
  exit 1
fi

if ! id "$LOGIN" >/dev/null 2>&1; then
  echo "Usuário Linux não encontrado"
  exit 1
fi

printf "%s\n%s\n" "$SENHA" "$SENHA" | smbpasswd "$LOGIN"

echo "OK"
