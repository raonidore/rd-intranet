#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
REL="${1:-}"

if echo "$REL" | grep -q '\.\.'; then
    echo '{"error":"Caminho invalido"}'; exit 1
fi

TARGET="$BASE"
[ -n "$REL" ] && TARGET="$BASE/$REL"

python3 - "$TARGET" "$BASE" << 'PYEOF'
import os, sys, json

target = sys.argv[1]
base   = sys.argv[2]

real = os.path.realpath(target) if os.path.exists(target) else target
if not real.startswith(base):
    print(json.dumps({"error":"Caminho invalido"})); sys.exit(1)
if not os.path.isdir(real):
    print(json.dumps({"error":"Diretorio nao encontrado"})); sys.exit(1)

items = []
try:
    for name in sorted(os.listdir(real), key=lambda n: (not os.path.isdir(os.path.join(real,n)), n.lower())):
        if name.startswith('.'): continue
        full = os.path.join(real, name)
        try:
            st = os.stat(full)
            is_dir = os.path.isdir(full)
            size = 0
            if is_dir:
                try:
                    import subprocess
                    r = subprocess.run(["du","-sb",full], capture_output=True, text=True, timeout=5)
                    size = int(r.stdout.split()[0]) if r.returncode == 0 else 0
                except: size = 0
            else:
                size = st.st_size
            items.append({"type":"dir" if is_dir else "file","name":name,"size":size,"modified":int(st.st_mtime)})
        except: pass
except Exception as e:
    print(json.dumps({"error":str(e)})); sys.exit(1)
print(json.dumps(items))
PYEOF
