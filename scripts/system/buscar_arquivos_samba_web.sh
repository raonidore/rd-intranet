#!/bin/bash
# buscar_arquivos_samba_web.sh <rel_pasta> <termo> <extensoes_csv>
#
# Busca recursiva de ARQUIVOS (nao pastas) a partir de <rel_pasta>,
# filtrando por substring do nome (<termo>, case-insensitive) e/ou lista
# de extensoes (<extensoes_csv>, ex: "mp3,exe,mp4") -- quando os dois sao
# informados, exige os dois (E logico). Mesmo esquema de seguranca de
# lista_arquivos_samba_web.sh (realpath + startswith), so que recursivo
# via os.walk em vez de um unico os.listdir.
#
# So retorna arquivos (nao pastas) de proposito: e o caso de uso real
# (achar/filtrar arquivos especificos, ex: .mp3 perdido num
# compartilhamento de documentos) e evita herdar o custo de `du -sb` por
# subpasta que a listagem normal paga (aqui nao precisa de tamanho de
# pasta nenhuma). Pastas cujo nome comeca com "." (ex: .recycle) sao
# puladas, mesmo criterio da listagem normal.
#
# Teto de resultados (MAX_RESULTADOS) na TABELA exibida, pra nao travar o
# navegador em compartilhamentos com dezenas de milhares de arquivos -- mas
# a contagem e a soma de tamanho ("total_real"/"bytes_total_real") sempre
# percorrem TODOS os arquivos correspondentes, nunca so os primeiros 1000.
# Isso importa de verdade: um numero errado aqui pode virar informacao
# errada repassada pro cliente (ex: "achei 1000 arquivos, 3.5GB" quando na
# real eram 1569 arquivos, 3.8GB -- ja aconteceu).

BASE="/srv/samba/Compartilhamentos"
REL="${1:-}"
TERMO="${2:-}"
EXTENSOES="${3:-}"

if echo "$REL" | grep -qE '(^|/)\.\.(/|$)'; then
    echo '{"error":"Caminho invalido"}'; exit 1
fi

TARGET="$BASE"
[ -n "$REL" ] && TARGET="$BASE/$REL"

python3 - "$TARGET" "$BASE" "$TERMO" "$EXTENSOES" << 'PYEOF'
import os, sys, json

target, base, termo, extensoes_csv = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
MAX_RESULTADOS = 1000

real = os.path.realpath(target) if os.path.exists(target) else target
if not real.startswith(base):
    print(json.dumps({"error": "Caminho invalido"})); sys.exit(1)
if not os.path.isdir(real):
    print(json.dumps({"error": "Diretorio nao encontrado"})); sys.exit(1)

termo_lower = termo.strip().lower()
extensoes = [e.strip().lower().lstrip('.') for e in extensoes_csv.split(',') if e.strip()]

itens = []
total_real = 0
bytes_total_real = 0

for raiz, dirs, arquivos in os.walk(real):
    dirs[:] = [d for d in dirs if not d.startswith('.')]
    dirs.sort()

    for nome in sorted(arquivos):
        if nome.startswith('.'):
            continue

        if termo_lower and termo_lower not in nome.lower():
            continue

        ext = nome.rsplit('.', 1)[-1].lower() if '.' in nome else ''
        if extensoes and ext not in extensoes:
            continue

        caminho_completo = os.path.join(raiz, nome)
        try:
            st = os.stat(caminho_completo)
        except OSError:
            continue

        # conta e soma SEMPRE (nunca para no teto) -- so a LISTA exibida
        # na tabela e limitada, os numeros de total tem que ser exatos
        total_real += 1
        bytes_total_real += st.st_size

        if len(itens) < MAX_RESULTADOS:
            rel_base = os.path.relpath(caminho_completo, base)
            itens.append({
                "type": "file",
                "name": nome,
                "size": st.st_size,
                "modified": int(st.st_mtime),
                "rel": rel_base.replace(os.sep, '/'),
            })

print(json.dumps({
    "itens": itens,
    "truncado": total_real > MAX_RESULTADOS,
    "total_real": total_real,
    "bytes_total_real": bytes_total_real,
}))
PYEOF
