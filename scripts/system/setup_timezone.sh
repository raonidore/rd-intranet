#!/bin/bash
# setup_timezone.sh
# Passo de instalacao/atualizacao (rodar uma vez, como root). NAO tem
# sufixo _web.sh de proposito -- roda dentro de atualizar_aplicar_web.sh
# (que ja executa como root via sudoers) e tambem e chamado direto pelo
# install.sh, mesmo criterio dos outros setup_*.sh.
#
# App\Core\Application::boot() fixa o PHP em America/Recife (-03, sem
# horario de verao no Brasil desde 2019) na marra, no codigo -- e o
# MySQL/MariaDB usa time_zone=SYSTEM (le o fuso do proprio SO). Se o SO
# do servidor nao estiver configurado pra um fuso -03 (comum em imagens
# de nuvem/VPS, que costumam vir em UTC por padrao), NOW() do banco sai
# em UTC enquanto o PHP le esse valor como se already fosse -03 --
# resultado: qualquer "ha quanto tempo" calculado a partir de timestamp
# do banco (ultimo_checkin, ultimo_heartbeat, etc.) fica errado em
# exatas 3 horas (180 min), pra mais ou pra menos dependendo da conta.
# Sem isso, servidor novo com SO em UTC mostra esse erro sempre.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

ANTES="$(timedatectl show --property=Timezone --value)"

timedatectl set-timezone America/Sao_Paulo

# O MariaDB fixa o fuso "SYSTEM" na inicializacao, nao relê /etc/localtime
# a cada consulta -- sem reiniciar, NOW() continua errado ate o proximo
# reboot do servidor mesmo depois do timedatectl acima. So reinicia se
# de fato mudou algo, pra nao derrubar conexoes a toa num servidor que
# ja estava correto.
if [ "$ANTES" != "America/Sao_Paulo" ] && systemctl is-active --quiet mariadb; then
  systemctl restart mariadb
fi

echo "OK"
