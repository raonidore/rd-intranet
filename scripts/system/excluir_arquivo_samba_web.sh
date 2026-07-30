#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
REL="$1"
if echo "$REL" | grep -q '\.\.'; then echo '{"success":false,"message":"Caminho invalido"}'; exit 1; fi
# "$REL" vai como ARGUMENTO do python (sys.argv), nunca interpolado direto
# na string de codigo -- um nome de arquivo com apostrofo (comum em
# portugues, ex: "d'oce") interpolado quebraria a string Python no meio
# e o realpath falharia silenciosamente (2>/dev/null escondia o erro),
# reportando "nao encontrado" pra um arquivo que existia.
REAL=$(python3 -c "import os,sys; p=os.path.realpath(sys.argv[1]); print(p if p.startswith(sys.argv[2]) else '')" "$BASE/$REL" "$BASE" 2>/dev/null)
[ -z "$REAL" ] || [ ! -e "$REAL" ] && echo '{"success":false,"message":"Arquivo nao encontrado"}' && exit 1
# Não permitir excluir raiz dos compartilhamentos (primeiro nível)
BASE_DEPTH=$(echo "$BASE" | tr -cd '/' | wc -c)
REAL_DEPTH=$(echo "$REAL" | tr -cd '/' | wc -c)
[ $REAL_DEPTH -le $((BASE_DEPTH+1)) ] && echo '{"success":false,"message":"Nao e permitido excluir compartilhamentos raiz"}' && exit 1
rm -rf "$REAL" 2>/dev/null && echo '{"success":true,"message":"Excluido com sucesso"}' || echo '{"success":false,"message":"Falha ao excluir"}'
