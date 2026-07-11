#!/bin/bash
# antivirus_verificar_web.sh <caminho>
# So aceita caminhos dentro de /srv/samba/Compartilhamentos (a raiz dos
# compartilhamentos geridos pela tela) -- nunca escaneia o sistema inteiro
# a partir de um valor vindo do formulario. Ameacas encontradas sao
# movidas para /var/quarantine/rd-intranet.
#
# Saida (uma linha por item, formato "chave|valor"):
#   TOTAL|<numero de arquivos verificados>
#   AMEACA|<arquivo original>|<arquivo em quarentena>|<assinatura>
#   (uma linha AMEACA por ameaca encontrada)

set -u

BASE="/srv/samba/Compartilhamentos"
CAMINHO="${1:-$BASE}"

if ! command -v clamdscan >/dev/null 2>&1 && ! command -v clamscan >/dev/null 2>&1; then
  echo "ERRO|ClamAV não está instalado."
  exit 1
fi

CAMINHO_REAL="$(readlink -f "$CAMINHO" 2>/dev/null || true)"
if [ -z "$CAMINHO_REAL" ] || [[ "$CAMINHO_REAL" != "$BASE"* ]]; then
  echo "ERRO|Caminho fora de ${BASE}."
  exit 1
fi

if [ ! -d "$CAMINHO_REAL" ]; then
  echo "ERRO|Diretório não encontrado: ${CAMINHO_REAL}"
  exit 1
fi

QUARENTENA="/var/quarantine/rd-intranet"
mkdir -p "$QUARENTENA"

if command -v clamdscan >/dev/null 2>&1; then
  SAIDA="$(clamdscan -m --fdpass -i --no-summary "$CAMINHO_REAL" 2>&1)"
else
  SAIDA="$(clamscan -r -i --no-summary "$CAMINHO_REAL" 2>&1)"
fi

echo "TOTAL|$(find "$CAMINHO_REAL" -type f 2>/dev/null | wc -l)"

echo "$SAIDA" | grep ": .* FOUND$" | while IFS= read -r LINHA; do
  SEM_FOUND="${LINHA% FOUND}"
  ARQUIVO="${SEM_FOUND%: *}"
  ASSINATURA="${SEM_FOUND##*: }"

  if [ ! -f "$ARQUIVO" ]; then
    continue
  fi

  DESTINO="${QUARENTENA}/$(date +%Y%m%d%H%M%S)_$(basename "$ARQUIVO")"
  if mv "$ARQUIVO" "$DESTINO" 2>/dev/null; then
    echo "AMEACA|${ARQUIVO}|${DESTINO}|${ASSINATURA}"
  else
    echo "AMEACA|${ARQUIVO}||${ASSINATURA}"
  fi
done
