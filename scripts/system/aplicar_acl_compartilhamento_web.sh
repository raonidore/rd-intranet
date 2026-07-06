#!/bin/bash
# aplicar_acl_compartilhamento_web.sh <nome_compartilhamento> <grupo> <login:leitura:escrita> [...]
# leitura/escrita = 0 ou 1
#
# Recalcula do zero a ACL de usuarios individuais de um compartilhamento via
# smbcacls (ACL do Windows/Samba, nao POSIX) -- autentica como a conta de
# servico svc_acl_admin (SeDiskOperatorPrivilege + admin users no smb.conf),
# usando o authfile gerado por setup_acl_admin.sh. "-S" substitui a ACL
# inteira do share pelas ACEs passadas (mesmo padrao "recalcula tudo" ja
# usado em salvar_permitidos_web.sh e em
# SambaCompartilhamentoRepository::salvarUsuariosAutorizados).
#
# So 2 niveis reais: leitura (RX) e escrita (+WD). Nao existe "exclusao"
# separada de "escrita" -- testado empiricamente: a lixeira (vfs_recycle,
# sempre ligada) faz o delete-de-dentro-de-.recycle direto no filesystem,
# sem passar pela checagem de ACL do Windows. Ou seja, quem tem escrita ja
# consegue apagar de verdade via a propria lixeira, entao a mascara de
# escrita ja inclui D (delete) para refletir o que de fato acontece.
#
# IMPORTANTE: -S troca a ACL inteira. Por isso o grupo dono do
# compartilhamento sempre entra como primeira ACE, com acesso completo --
# sem isso, os membros do grupo perderiam acesso no primeiro save desta
# tela, mesmo sem nunca terem sido listados aqui. "read only" continua
# sendo reforcado no nivel do smb.conf (share), esta ACL nao substitui isso.

AUTHFILE="/opt/rdtecnologia/scripts/.smbacl_auth"
SHARE="$1"
GRUPO="$2"
shift 2

if [ ! -f "$AUTHFILE" ]; then
  echo "Authfile nao encontrado. Rode setup_acl_admin.sh primeiro."
  exit 1
fi

if [[ ! "$SHARE" =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "Nome de compartilhamento invalido"
  exit 1
fi

if [[ ! "$GRUPO" =~ ^[a-z][a-z0-9_-]*$ ]]; then
  echo "Grupo invalido"
  exit 1
fi

ACES="REVISION:1,OWNER:root,GROUP:${GRUPO},ACL:${GRUPO}:ALLOWED/OI|CI/RWD"

for ITEM in "$@"; do
  IFS=':' read -r LOGIN LEITURA ESCRITA <<< "$ITEM"

  if [[ ! "$LOGIN" =~ ^[a-z0-9]+$ ]]; then
    echo "Login invalido: $LOGIN"
    exit 1
  fi

  MASK=""
  [ "$LEITURA" = "1" ] && MASK="${MASK}RX"
  [ "$ESCRITA" = "1" ] && MASK="${MASK}WD"

  if [ -z "$MASK" ]; then
    continue
  fi

  ACES="${ACES},ACL:${LOGIN}:ALLOWED/OI|CI/${MASK}"
done

smbcacls "//localhost/${SHARE}" "" -A "$AUTHFILE" -S "$ACES"
