#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
REL="$1"
if echo "$REL" | grep -q '\.\.'; then echo '{"success":false,"message":"Caminho invalido"}'; exit 1; fi
REAL=$(python3 -c "import os; p=os.path.normpath('$BASE/$REL'); print(p if p.startswith('$BASE') else '')" 2>/dev/null)
[ -z "$REAL" ] && echo '{"success":false,"message":"Caminho invalido"}' && exit 1
[ -e "$REAL" ] && echo '{"success":false,"message":"Ja existe"}' && exit 1
mkdir -p "$REAL" 2>/dev/null && chmod 2770 "$REAL" 2>/dev/null && echo '{"success":true,"message":"Pasta criada com sucesso"}' || echo '{"success":false,"message":"Falha ao criar pasta"}'
