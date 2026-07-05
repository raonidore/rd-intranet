#!/bin/bash

LOGIN="$1"
NOME="$2"
GRUPO="$3"
SSH="$4"

if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
  echo "Login inválido"
  exit 1
fi

case "$GRUPO" in
  ti|financeiro|cobranca) ;;
  *)
    echo "Grupo inválido"
    exit 1
    ;;
esac

if ! id "$LOGIN" >/dev/null 2>&1; then
  echo "Usuário Linux não encontrado"
  exit 1
fi

if [ "$LOGIN" = "ti" ] && [ "$GRUPO" != "ti" ]; then
  echo "O usuário administrativo principal deve permanecer no grupo TI"
  exit 1
fi

if [ "$SSH" = "sim" ]; then
  SHELL="/bin/bash"
else
  SHELL="/usr/sbin/nologin"
fi

usermod -c "$NOME" -s "$SHELL" "$LOGIN"

gpasswd -d "$LOGIN" ti >/dev/null 2>&1 || true
gpasswd -d "$LOGIN" financeiro >/dev/null 2>&1 || true
gpasswd -d "$LOGIN" cobranca >/dev/null 2>&1 || true

usermod -aG smbusers "$LOGIN"
usermod -aG "$GRUPO" "$LOGIN"

echo "OK"
