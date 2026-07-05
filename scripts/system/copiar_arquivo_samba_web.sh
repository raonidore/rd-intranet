#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
SRC_REL="$1"
DEST_DIR_REL="$2"

for v in "$SRC_REL" "$DEST_DIR_REL"; do
    echo "$v" | grep -q '\.\.' && echo '{"success":false,"message":"Caminho invalido"}' && exit 1
done

REAL_SRC=$(python3 -c "import os; p=os.path.realpath('$BASE/$SRC_REL'); print(p if p.startswith('$BASE') else '')" 2>/dev/null)
[ -z "$REAL_SRC" ] || [ ! -e "$REAL_SRC" ] && echo '{"success":false,"message":"Origem nao encontrada"}' && exit 1

REAL_DEST=$(python3 -c "import os; p=os.path.realpath('$BASE/$DEST_DIR_REL'); print(p if p.startswith('$BASE') else '')" 2>/dev/null)
[ -z "$REAL_DEST" ] || [ ! -d "$REAL_DEST" ] && echo '{"success":false,"message":"Destino nao encontrado"}' && exit 1

DEST_FILE="$REAL_DEST/$(basename "$REAL_SRC")"
[ -e "$DEST_FILE" ] && echo '{"success":false,"message":"Ja existe um item com este nome no destino"}' && exit 1

cp -r "$REAL_SRC" "$REAL_DEST/" 2>/dev/null && echo '{"success":true,"message":"Copiado com sucesso"}' || echo '{"success":false,"message":"Falha ao copiar"}'
