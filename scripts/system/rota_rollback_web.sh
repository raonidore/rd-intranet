#!/bin/bash
# rota_rollback_web.sh <destino_cidr>
# Disparado automaticamente pelo systemd-run agendado em rota_aplicar_web.sh
# caso a rota nao seja confirmada dentro do prazo. So remove a rota ao vivo
# -- nunca foi persistida em rotas-extras.conf, entao nao ha nada pra
# desfazer la.

DESTINO="$1"

ip route del "$DESTINO" >/dev/null 2>&1
rm -f /etc/rd-intranet/.rota-pendente /etc/rd-intranet/.rota-deadline

logger -t rd-rotas "Rollback automatico da rota $DESTINO executado (nao confirmada dentro do prazo)."
