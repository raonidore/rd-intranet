#!/bin/bash
# Sincroniza scripts/system/ (versionado no git) para /opt/rdtecnologia/scripts/
# (raiz, fora do repo). Rodar como root apos qualquer alteracao em scripts/system/:
#
#   sudo /var/www/rd.intranet/scripts/sync-system-scripts.sh
#
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

ORIGEM="$(cd "$(dirname "${BASH_SOURCE[0]}")/system" && pwd)"
DESTINO="/opt/rdtecnologia/scripts"

mkdir -p "$DESTINO"
cp -a "$ORIGEM"/*.sh "$DESTINO"/
chown root:root "$DESTINO"/*.sh
chmod 755 "$DESTINO"/*.sh

echo "OK: $(ls "$ORIGEM"/*.sh | wc -l) scripts sincronizados para $DESTINO"
