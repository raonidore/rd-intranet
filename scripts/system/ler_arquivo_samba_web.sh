#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
REL="$1"
if echo "$REL" | grep -qE '(^|/)\.\.(/|$)'; then exit 1; fi
# "$REL" via sys.argv, nao interpolado -- ver comentario em excluir_arquivo_samba_web.sh
REAL=$(python3 -c "import os,sys; p=os.path.realpath(sys.argv[1]); print(p if p.startswith(sys.argv[2]) else '')" "$BASE/$REL" "$BASE" 2>/dev/null)
[ -z "$REAL" ] || [ ! -f "$REAL" ] && exit 1
cat "$REAL"
