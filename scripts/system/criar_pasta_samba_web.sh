#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
REL="$1"
if echo "$REL" | grep -q '\.\.'; then echo '{"success":false,"message":"Caminho invalido"}'; exit 1; fi
# caminho via sys.argv, nao interpolado na string -- ver comentario em
# excluir_arquivo_samba_web.sh (apostrofo no nome quebrava a string Python)
REAL=$(python3 -c "import os,sys; p=os.path.normpath(sys.argv[1]); print(p if p.startswith(sys.argv[2]) else '')" "$BASE/$REL" "$BASE" 2>/dev/null)
[ -z "$REAL" ] && echo '{"success":false,"message":"Caminho invalido"}' && exit 1
[ -e "$REAL" ] && echo '{"success":false,"message":"Ja existe"}' && exit 1
mkdir -p "$REAL" 2>/dev/null && chmod 2770 "$REAL" 2>/dev/null && echo '{"success":true,"message":"Pasta criada com sucesso"}' || echo '{"success":false,"message":"Falha ao criar pasta"}'
