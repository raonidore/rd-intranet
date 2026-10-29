#!/bin/bash

NOME="$1"
LOGIN="$2"
GRUPO="$3"
SSH="$4"
# Senha pela entrada padrão (uma linha), nunca por argumento: argumento aparece no
# log do sudo/journal e no "ps" de qualquer usuário.
IFS= read -r SENHA

# Para no primeiro erro: antes cada passo falhava em silêncio e o script
# terminava com "OK" (mda-dc, 29/10/2026: usuário só no portal, UID 0).
falhou() {
  echo "Falha: $1"
  exit 1
}

if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
  echo "Login inválido"
  exit 1
fi

if [ -z "$SENHA" ]; then
  echo "Senha não informada"
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
  groupadd "$GRUPO" || falhou "não foi possível criar o grupo $GRUPO"
fi

getent group smbusers >/dev/null || groupadd smbusers || falhou "não foi possível criar o grupo smbusers"

if [ "$SSH" = "sim" ]; then
  SHELL="/bin/bash"
else
  SHELL="/usr/sbin/nologin"
fi

# Grupo com o mesmo nome do login (ex.: login "arlab" no grupo "arlab"): o adduser
# recusa criar o grupo pessoal se ele já existe -- usa o existente como primário.
if getent group "$LOGIN" >/dev/null; then
  adduser --disabled-password --shell "$SHELL" --gecos "$NOME,,," --ingroup "$LOGIN" "$LOGIN" \
    || falhou "adduser não criou o usuário $LOGIN"
else
  adduser --disabled-password --shell "$SHELL" --gecos "$NOME,,," "$LOGIN" \
    || falhou "adduser não criou o usuário $LOGIN"
fi

echo "$LOGIN:$SENHA" | chpasswd || falhou "não foi possível definir a senha Linux"

usermod -aG smbusers "$LOGIN" || falhou "não foi possível incluir no grupo smbusers"
usermod -aG "$GRUPO" "$LOGIN" || falhou "não foi possível incluir no grupo $GRUPO"

printf "%s\n%s\n" "$SENHA" "$SENHA" | smbpasswd -s -a "$LOGIN" || falhou "smbpasswd não cadastrou o usuário no Samba"
smbpasswd -e "$LOGIN" || falhou "smbpasswd não habilitou o usuário"

echo "OK"
