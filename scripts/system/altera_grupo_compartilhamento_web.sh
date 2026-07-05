#!/bin/bash
# altera_grupo_compartilhamento_web.sh <caminho> <grupo>
# Re-sincroniza o dono de grupo Unix de um compartilhamento quando o campo
# "grupo" é alterado na tela de edição. Sem isso, o banco e o disco divergem
# silenciosamente (a tela salva um grupo novo, mas os arquivos continuam
# pertencendo ao grupo antigo).

CAMINHO="$1"
GRUPO="$2"

if [[ ! "$GRUPO" =~ ^[a-z][a-z0-9_-]*$ ]]; then
  echo "Grupo inválido"
  exit 1
fi

if [[ "$CAMINHO" != /srv/samba/Compartilhamentos/* ]]; then
  echo "Caminho inválido. Use /srv/samba/Compartilhamentos/"
  exit 1
fi

if [ ! -d "$CAMINHO" ]; then
  echo "Caminho não encontrado"
  exit 1
fi

if ! getent group "$GRUPO" >/dev/null; then
  groupadd "$GRUPO"
fi

chown -R root:"$GRUPO" "$CAMINHO"
chmod 2770 "$CAMINHO"

echo "OK"
