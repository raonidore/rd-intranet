#!/bin/bash
# rota_excluir_web.sh <destino_cidr>
# So remove rotas que a propria RD Intranet gerencia (presentes em
# rotas-extras.conf) -- nunca uma rota qualquer do sistema (ex: default
# gateway gerenciado pelo netplan), pra nao derrubar conectividade por
# engano a partir da tela de Rotas.

DESTINO="$1"
ARQUIVO="/etc/rd-intranet/rotas-extras.conf"

if [ ! -f "$ARQUIVO" ] || ! grep -q "^${DESTINO} " "$ARQUIVO"; then
  echo '{"success":false,"message":"Esta rota nao e gerenciada pela RD Intranet, nao pode ser excluida por aqui."}'
  exit 1
fi

ip route del "$DESTINO" >/dev/null 2>&1

grep -v "^${DESTINO} " "$ARQUIVO" > "${ARQUIVO}.tmp" || true
mv "${ARQUIVO}.tmp" "$ARQUIVO"

echo '{"success":true,"message":"Rota removida com sucesso."}'
