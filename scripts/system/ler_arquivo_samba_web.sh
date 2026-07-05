#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
REL="$1"
if echo "$REL" | grep -q '\.\.'; then exit 1; fi
REAL=$(python3 -c "import os,sys; p=os.path.realpath('$BASE/$REL'); print(p if p.startswith('$BASE') else '')" 2>/dev/null)
[ -z "$REAL" ] || [ ! -f "$REAL" ] && exit 1
cat "$REAL"
