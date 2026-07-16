#!/bin/bash

NOME="$1"
CAMINHO="$2"
GRUPO="$3"

if [[ ! "$NOME" =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "Nome inválido"
  exit 1
fi

if [[ ! "$GRUPO" =~ ^[a-z0-9_-]+$ ]]; then
  echo "Grupo inválido"
  exit 1
fi

if [[ "$CAMINHO" != /srv/samba/Compartilhamentos/* ]]; then
  echo "Caminho inválido. Use /srv/samba/Compartilhamentos/"
  exit 1
fi

if ! getent group "$GRUPO" >/dev/null; then
  groupadd "$GRUPO"
fi

mkdir -p "$CAMINHO"

# -R: se o caminho já tinha conteúdo antes de virar compartilhamento (dados
# migrados de outro servidor, por exemplo), esse conteúdo também precisa do
# grupo/permissão certos -- sem -R só a pasta raiz ficava correta, e os
# arquivos/subpastas antigos ficavam com o dono/grupo de origem. Pastas
# recebem 2770 (setgid, novos itens herdam o grupo); arquivos recebem 0660
# (sem setgid/execução -- setgid em arquivo comum não faz sentido aqui e
# só chamaria atenção à toa em auditoria de segurança).
chown -R root:"$GRUPO" "$CAMINHO"
find "$CAMINHO" -type d -exec chmod 2770 {} +
find "$CAMINHO" -type f -exec chmod 0660 {} +

echo "OK"
