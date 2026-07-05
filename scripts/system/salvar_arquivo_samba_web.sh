#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
TMPFILE="$1"
REL="$2"
if echo "$REL" | grep -q '\.\.'; then echo '{"success":false,"message":"Caminho invalido"}'; exit 1; fi
REAL=$(python3 -c "import os; p=os.path.normpath('$BASE/$REL'); print(p if p.startswith('$BASE') else '')" 2>/dev/null)
[ -z "$REAL" ] && echo '{"success":false,"message":"Caminho invalido"}' && exit 1
[ ! -f "$TMPFILE" ] && echo '{"success":false,"message":"Arquivo temporario nao encontrado"}' && exit 1
DEST_DIR=$(dirname "$REAL")
[ ! -d "$DEST_DIR" ] && echo '{"success":false,"message":"Diretorio destino nao existe"}' && exit 1
cp "$TMPFILE" "$REAL" 2>/dev/null && chmod 770 "$REAL" 2>/dev/null && echo '{"success":true,"message":"Arquivo salvo com sucesso"}' || echo '{"success":false,"message":"Falha ao salvar"}'
