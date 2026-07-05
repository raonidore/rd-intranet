#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
REL="$1"
if echo "$REL" | grep -q '\.\.'; then echo '{"success":false,"message":"Caminho invalido"}'; exit 1; fi
REAL=$(python3 -c "import os; p=os.path.realpath('$BASE/$REL'); print(p if p.startswith('$BASE') else '')" 2>/dev/null)
[ -z "$REAL" ] || [ ! -e "$REAL" ] && echo '{"success":false,"message":"Arquivo nao encontrado"}' && exit 1
# Não permitir excluir raiz dos compartilhamentos (primeiro nível)
BASE_DEPTH=$(echo "$BASE" | tr -cd '/' | wc -c)
REAL_DEPTH=$(echo "$REAL" | tr -cd '/' | wc -c)
[ $REAL_DEPTH -le $((BASE_DEPTH+1)) ] && echo '{"success":false,"message":"Nao e permitido excluir compartilhamentos raiz"}' && exit 1
rm -rf "$REAL" 2>/dev/null && echo '{"success":true,"message":"Excluido com sucesso"}' || echo '{"success":false,"message":"Falha ao excluir"}'
