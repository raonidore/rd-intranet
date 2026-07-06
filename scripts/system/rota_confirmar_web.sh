#!/bin/bash
# rota_confirmar_web.sh
# Cancela o rollback automatico agendado e persiste a rota pendente
# (lida de /etc/rd-intranet/.rota-pendente, gravada por rota_aplicar_web.sh
# -- nao depende do navegador lembrar os dados apos um reload de pagina)
# em /etc/rd-intranet/rotas-extras.conf.

PENDENTE="/etc/rd-intranet/.rota-pendente"
ARQUIVO="/etc/rd-intranet/rotas-extras.conf"

if ! systemctl is-active --quiet rd-rota-rollback.timer; then
  echo '{"success":false,"message":"Nao ha alteracao pendente de confirmacao."}'
  exit 1
fi

if [ ! -f "$PENDENTE" ]; then
  echo '{"success":false,"message":"Nao foi encontrado o registro da rota pendente."}'
  exit 1
fi

LINHA="$(cat "$PENDENTE")"

systemctl stop rd-rota-rollback.timer >/dev/null 2>&1
systemctl reset-failed rd-rota-rollback >/dev/null 2>&1

mkdir -p /etc/rd-intranet
touch "$ARQUIVO"

if ! grep -qxF "$LINHA" "$ARQUIVO"; then
  echo "$LINHA" >> "$ARQUIVO"
fi

rm -f "$PENDENTE" /etc/rd-intranet/.rota-deadline

systemctl enable rd-rotas-extras.service >/dev/null 2>&1

echo '{"success":true,"message":"Rota confirmada e mantida definitivamente (sobrevive a reinicializacao)."}'
