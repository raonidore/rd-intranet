#!/bin/bash
# dependencias_instalar_web.sh <chave>
#
# Instala uma ferramenta que a RD Intranet usa, via apt. So aceita as
# chaves desta lista travada (nunca um nome de pacote vindo de fora) --
# mesmo criterio de allowlist-no-proprio-script ja usado em services_web.sh
# e cron_aplicar_web.sh: um bug/CSRF na tela nao pode instalar qualquer
# coisa, so o que esta pre-aprovado aqui.

set -u

CHAVE="$1"

declare -A PACOTES=(
  [iptables]="iptables"
  [conntrack]="conntrack"
  [openssl]="openssl"
  [certbot]="certbot"
  [cron]="cron"
  [lm-sensors]="lm-sensors"
  [mariadb-client]="mariadb-client"
  [samba]="samba"
  [iproute2]="iproute2"
  [python3]="python3"
  [smbclient]="smbclient"
  [apache2]="apache2"
  [openssh-server]="openssh-server"
  [netplan]="netplan.io"
  [samba-common-bin]="samba-common-bin"
  [acl]="acl"
  [traceroute]="traceroute"
  [iputils-ping]="iputils-ping"
)

PACOTE="${PACOTES[$CHAVE]:-}"

if [ -z "$PACOTE" ]; then
  echo '{"success":false,"message":"Ferramenta desconhecida (fora da lista permitida)."}'
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

# "-qq" so silencia o progresso do proprio apt -- o dpkg (chamado por
# baixo) continua imprimindo "Unpacking.../Setting up..." no stdout
# mesmo em modo nao interativo, o que quebrava o json_decode() do lado
# PHP (mesmo bug ja corrigido em antivirus_instalar_web.sh). Por isso o
# stdout tambem vai pro arquivo temporario, nao só o stderr.
if ! apt-get update -qq >/tmp/rd_dep_out_$$ 2>/tmp/rd_dep_err_$$; then
  ERRO="$(tail -10 /tmp/rd_dep_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_dep_out_$$ /tmp/rd_dep_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao atualizar lista de pacotes: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_dep_out_$$ /tmp/rd_dep_err_$$

if ! apt-get install -y -qq "$PACOTE" >/tmp/rd_dep_out_$$ 2>/tmp/rd_dep_err_$$; then
  ERRO="$(tail -20 /tmp/rd_dep_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_dep_out_$$ /tmp/rd_dep_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar ${PACOTE}: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_dep_out_$$ /tmp/rd_dep_err_$$

echo "{\"success\":true,\"message\":\"${PACOTE} instalado com sucesso.\"}"
