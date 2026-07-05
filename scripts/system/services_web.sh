#!/bin/bash
# services_web.sh <unidade> <acao>
# <unidade> e o nome real da unidade systemd (ex: smbd, apache2, mariadb, ssh, cron, ...).
# So atua sobre unidades presentes na allowlist gravada por salvar_permitidos_web.sh
# (lista que o admin escolhe na tela de Configurar Servicos), nunca em qualquer
# unidade do systemd -- isso limita o raio de acao de um CSRF/bug na tela de
# servicos as unidades que o admin efetivamente aprovou.

SERVICE="$1"
ACTION="$2"

ALLOWLIST="/opt/rdtecnologia/scripts/.servicos_permitidos"
PADRAO=$'smbd\napache2\nmariadb\nssh'

# valida formato do nome (letras, numeros, . _ - @)
if ! [[ "$SERVICE" =~ ^[a-zA-Z0-9@_.-]+$ ]]; then
  echo "Servico invalido"
  exit 1
fi

if [ -s "$ALLOWLIST" ]; then
  PERMITIDOS="$(cat "$ALLOWLIST")"
else
  PERMITIDOS="$PADRAO"
fi

if ! grep -qxF "$SERVICE" <<< "$PERMITIDOS"; then
  echo "Servico nao esta na lista de servicos gerenciados"
  exit 1
fi

UNIT="${SERVICE}.service"

# confirma que a unidade existe de fato no systemd antes de agir sobre ela
if ! systemctl list-unit-files "$UNIT" --no-legend 2>/dev/null | grep -q "^${UNIT}[[:space:]]"; then
  echo "Servico nao encontrado"
  exit 1
fi

case "$ACTION" in
  status)
    echo "SERVICE=$SERVICE"
    echo "UNIT=$UNIT"
    echo "STATUS=$(systemctl is-active "$UNIT")"
    echo "ENABLED=$(systemctl is-enabled "$UNIT" 2>/dev/null || echo desconhecido)"
    echo "UPTIME=$(systemctl show "$UNIT" --property=ActiveEnterTimestamp --value)"
    ;;
  restart)
    systemctl restart "$UNIT"
    echo "OK"
    ;;
  reload)
    systemctl reload "$UNIT" 2>/dev/null || systemctl restart "$UNIT"
    echo "OK"
    ;;
  logs)
    journalctl -u "$UNIT" -n 80 --no-pager
    ;;
  *)
    echo "Acao invalida"
    exit 1
    ;;
esac
