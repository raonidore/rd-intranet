#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
SRC_REL="$1"
DEST_DIR_REL="$2"

for v in "$SRC_REL" "$DEST_DIR_REL"; do
    echo "$v" | grep -q '\.\.' && echo '{"success":false,"message":"Caminho invalido"}' && exit 1
done

# caminhos via sys.argv, nao interpolados na string -- ver comentario em
# excluir_arquivo_samba_web.sh (apostrofo no nome quebrava a string Python)
REAL_SRC=$(python3 -c "import os,sys; p=os.path.realpath(sys.argv[1]); print(p if p.startswith(sys.argv[2]) else '')" "$BASE/$SRC_REL" "$BASE" 2>/dev/null)
[ -z "$REAL_SRC" ] || [ ! -e "$REAL_SRC" ] && echo '{"success":false,"message":"Origem nao encontrada"}' && exit 1

REAL_DEST=$(python3 -c "import os,sys; p=os.path.realpath(sys.argv[1]); print(p if p.startswith(sys.argv[2]) else '')" "$BASE/$DEST_DIR_REL" "$BASE" 2>/dev/null)
[ -z "$REAL_DEST" ] || [ ! -d "$REAL_DEST" ] && echo '{"success":false,"message":"Destino nao encontrado"}' && exit 1

# Impedir mover pasta para dentro de si mesma
[[ "$REAL_DEST" == "$REAL_SRC" || "$REAL_DEST" == "$REAL_SRC/"* ]] && echo '{"success":false,"message":"Nao e possivel mover para dentro da propria pasta"}' && exit 1

DEST_FILE="$REAL_DEST/$(basename "$REAL_SRC")"
[ -e "$DEST_FILE" ] && echo '{"success":false,"message":"Ja existe um item com este nome no destino"}' && exit 1

mv "$REAL_SRC" "$REAL_DEST/" 2>/dev/null && echo '{"success":true,"message":"Movido com sucesso"}' || echo '{"success":false,"message":"Falha ao mover"}'
