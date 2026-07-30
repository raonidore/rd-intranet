#!/bin/bash
BASE="/srv/samba/Compartilhamentos"
REL_ANTIGO="$1"
NOVO_NOME="$2"

if echo "$REL_ANTIGO" | grep -qE '(^|/)\.\.(/|$)'; then
    echo '{"success":false,"message":"Caminho invalido"}'; exit 1
fi
if [ -z "$NOVO_NOME" ] || echo "$NOVO_NOME" | grep -qP '[<>:"/\\|?*\x00-\x1f]'; then
    echo '{"success":false,"message":"Nome invalido"}'; exit 1
fi

# caminho via sys.argv, nao interpolado na string -- ver comentario em
# excluir_arquivo_samba_web.sh (apostrofo no nome quebrava a string Python)
REAL_ANTIGO=$(python3 -c "
import os, sys
p = os.path.realpath(sys.argv[1])
print(p if p.startswith(sys.argv[2]) else '')
" "$BASE/$REL_ANTIGO" "$BASE" 2>/dev/null)

[ -z "$REAL_ANTIGO" ] || [ ! -e "$REAL_ANTIGO" ] && echo '{"success":false,"message":"Item nao encontrado"}' && exit 1

# Nao permitir renomear raiz dos compartilhamentos
BASE_DEPTH=$(echo "$BASE" | tr -cd '/' | wc -c)
REAL_DEPTH=$(echo "$REAL_ANTIGO" | tr -cd '/' | wc -c)
[ $REAL_DEPTH -le $((BASE_DEPTH+1)) ] && echo '{"success":false,"message":"Nao e permitido renomear compartilhamentos raiz"}' && exit 1

DESTINO="$(dirname "$REAL_ANTIGO")/$NOVO_NOME"

[ -e "$DESTINO" ] && echo '{"success":false,"message":"Ja existe um item com este nome"}' && exit 1

mv "$REAL_ANTIGO" "$DESTINO" 2>/dev/null && echo '{"success":true,"message":"Renomeado com sucesso"}' || echo '{"success":false,"message":"Falha ao renomear"}'
