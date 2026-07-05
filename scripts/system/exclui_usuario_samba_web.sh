#!/bin/bash

LOGIN="$1"

if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
  echo "Login inválido"
  exit 1
fi

if [ "$LOGIN" = "ti" ]; then
  echo "O usuário administrativo principal não pode ser excluído"
  exit 1
fi

if ! id "$LOGIN" >/dev/null 2>&1; then
  echo "Usuário Linux não encontrado"
  exit 1
fi

smbpasswd -x "$LOGIN" >/dev/null 2>&1 || true
userdel -r "$LOGIN"

echo "OK"
