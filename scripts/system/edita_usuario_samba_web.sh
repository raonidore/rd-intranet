#!/bin/bash
# edita_usuario_samba_web.sh <login> <nome> <grupo_novo> <ssh> <grupo_antigo>

LOGIN="$1"
NOME="$2"
GRUPO="$3"
SSH="$4"
GRUPO_ANTIGO="$5"

if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
  echo "Login inválido"
  exit 1
fi

if [[ ! "$GRUPO" =~ ^[a-z][a-z0-9_-]*$ ]]; then
  echo "Grupo inválido"
  exit 1
fi

if ! id "$LOGIN" >/dev/null 2>&1; then
  echo "Usuário Linux não encontrado"
  exit 1
fi

if [ "$LOGIN" = "ti" ] && [ "$GRUPO" != "ti" ]; then
  echo "O usuário administrativo principal deve permanecer no grupo TI"
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

usermod -c "$NOME" -s "$SHELL" "$LOGIN"

if [ -n "$GRUPO_ANTIGO" ] && [ "$GRUPO_ANTIGO" != "$GRUPO" ]; then
  gpasswd -d "$LOGIN" "$GRUPO_ANTIGO" >/dev/null 2>&1 || true
fi

usermod -aG smbusers "$LOGIN"
usermod -aG "$GRUPO" "$LOGIN"

echo "OK"
