#!/bin/bash
# renomear_grupo_web.sh <grupo_antigo> <grupo_novo>
#
# Renomeia um grupo Linux real (groupmod -n). O GID nao muda, entao
# arquivos ja existentes (chown por GID) e ACLs ja aplicadas via smbcacls
# (que resolvem por SID/GID, nao por nome) continuam validas -- so o
# nome de exibicao muda. O que quebra na hora e o smb.conf (referencia o
# nome antigo como texto puro em "valid users"/"force group"), por isso
# o chamador PHP precisa re-aplicar o deploy do Samba logo em seguida,
# nao so marcar como pendente.

ANTIGO="$1"
NOVO="$2"

if [[ ! "$ANTIGO" =~ ^[a-z][a-z0-9_-]*$ ]] || [[ ! "$NOVO" =~ ^[a-z][a-z0-9_-]*$ ]]; then
  echo "Nome de grupo invalido"
  exit 1
fi

if [ "$ANTIGO" = "$NOVO" ]; then
  echo "O novo nome e igual ao atual"
  exit 1
fi

if ! getent group "$ANTIGO" >/dev/null; then
  echo "Grupo '$ANTIGO' nao existe"
  exit 1
fi

if getent group "$NOVO" >/dev/null; then
  echo "Ja existe um grupo chamado '$NOVO'"
  exit 1
fi

groupmod -n "$NOVO" "$ANTIGO"

echo "OK"
