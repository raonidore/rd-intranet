#!/bin/bash

LOGIN="$1"
# Senha pela entrada padrão (uma linha), nunca por argumento: argumento aparece no
# log do sudo/journal e no "ps" de qualquer usuário.
IFS= read -r SENHA

if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
  echo "Login inválido"
  exit 1
fi

if [ -z "$SENHA" ]; then
  echo "Senha não informada"
  exit 1
fi

if ! id "$LOGIN" >/dev/null 2>&1; then
  echo "Usuário Linux não encontrado"
  exit 1
fi

printf "%s\n%s\n" "$SENHA" "$SENHA" | smbpasswd -s "$LOGIN" || { echo "smbpasswd não alterou a senha"; exit 1; }

echo "OK"
