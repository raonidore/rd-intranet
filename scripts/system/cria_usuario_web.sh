#!/bin/bash

NOME="$1"
LOGIN="$2"
GRUPO="$3"
SSH="$4"
SENHA="$5"

if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
  echo "Login inválido"
  exit 1
fi

if [[ ! "$GRUPO" =~ ^[a-z][a-z0-9_-]*$ ]]; then
  echo "Grupo inválido"
  exit 1
fi

if id "$LOGIN" >/dev/null 2>&1; then
  echo "Usuário já existe"
  exit 1
fi

if ! getent group "$GRUPO" >/dev/null; then
  groupadd "$GRUPO"
fi

if [ "$SSH" = "sim" ]; then
  SHELL="/bin/bash"
else
  SHELL="/usr/sbin/nologin"
fi

adduser --disabled-password --shell "$SHELL" --gecos "$NOME,,," "$LOGIN"

echo "$LOGIN:$SENHA" | chpasswd

usermod -aG smbusers "$LOGIN"
usermod -aG "$GRUPO" "$LOGIN"

printf "%s\n%s\n" "$SENHA" "$SENHA" | smbpasswd -a "$LOGIN"
smbpasswd -e "$LOGIN"

echo "OK"
