#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
TMPFILE="$1"
REL="$2"
if echo "$REL" | grep -qE '(^|/)\.\.(/|$)'; then echo '{"success":false,"message":"Caminho invalido"}'; exit 1; fi
# caminho via sys.argv, nao interpolado na string -- ver comentario em
# excluir_arquivo_samba_web.sh (apostrofo no nome quebrava a string Python)
REAL=$(python3 -c "import os,sys; p=os.path.normpath(sys.argv[1]); print(p if p.startswith(sys.argv[2]) else '')" "$BASE/$REL" "$BASE" 2>/dev/null)
[ -z "$REAL" ] && echo '{"success":false,"message":"Caminho invalido"}' && exit 1
[ ! -f "$TMPFILE" ] && echo '{"success":false,"message":"Arquivo temporario nao encontrado"}' && exit 1
DEST_DIR=$(dirname "$REAL")
[ ! -d "$DEST_DIR" ] && echo '{"success":false,"message":"Diretorio destino nao existe"}' && exit 1
cp "$TMPFILE" "$REAL" 2>/dev/null && chmod 770 "$REAL" 2>/dev/null && echo '{"success":true,"message":"Arquivo salvo com sucesso"}' || echo '{"success":false,"message":"Falha ao salvar"}'
