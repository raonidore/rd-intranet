#!/bin/bash
# speedtest_instalar_web.sh
# Adiciona o repositorio oficial da Ookla (packagecloud) e instala o
# Speedtest CLI. Feito de forma explicita/auditavel (baixa a chave GPG e
# escreve o sources.list.d na mao) -- de proposito NAO usa
# "curl | sudo bash" (nenhum outro script deste repo instala as cegas via
# pipe). URLs/formato conferidos ao vivo antes de escrever este script:
#   curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/config_file.list?os=ubuntu&dist=noble&source=script
# ATENCAO: esse endpoint devolve uma config valida pra qualquer dist
# pedido, mesmo pra codinomes sem repositorio publicado de verdade (ex:
# "noble" -- confirmado em producao: "does not have a Release file").
# Por isso o script abaixo confere se o Release existe antes de usar o
# codinome real, com fallback pro ultimo LTS que a Ookla publicou.
#
# Tambem cria /var/lib/rd-intranet/speedtest (dono www-data) -- e o $HOME
# que speedtest_executar_web.sh vai usar depois (sem sudo), porque o
# www-data nao tem home de verdade e o binario grava a aceitacao da
# licenca em $HOME/.config na primeira execucao.

set -u

CHAVEIRO="/etc/apt/keyrings/ookla_speedtest-cli-archive-keyring.gpg"
REPO_ARQUIVO="/etc/apt/sources.list.d/ookla_speedtest-cli.list"

export DEBIAN_FRONTEND=noninteractive

if ! apt-get install -y -qq gnupg ca-certificates >/tmp/rd_st_out_$$ 2>/tmp/rd_st_err_$$; then
  ERRO="$(tail -10 /tmp/rd_st_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_st_out_$$ /tmp/rd_st_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar pre-requisitos (gnupg/ca-certificates): ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_st_out_$$ /tmp/rd_st_err_$$

mkdir -p /etc/apt/keyrings

# GNUPGHOME proprio e descartavel: rodando via sudo a partir do PHP, o
# HOME herdado costuma vir vazio/incoerente, e o gpg tenta resolver
# ~/.gnupg sozinho -- em alguns casos isso dispara uma checagem de
# terminal e falha com "cannot open /dev/tty" mesmo com --no-tty. Dar um
# home isolado e gravavel evita o gpg tocar em qualquer estado
# ambiente/herdado.
GNUPGHOME_TMP="$(mktemp -d)"
chmod 700 "$GNUPGHOME_TMP"

if ! curl -fsSL "https://packagecloud.io/ookla/speedtest-cli/gpgkey" 2>/tmp/rd_st_err_$$ \
    | GNUPGHOME="$GNUPGHOME_TMP" gpg --batch --yes --no-tty --dearmor -o "$CHAVEIRO" 2>>/tmp/rd_st_err_$$; then
  ERRO="$(tail -10 /tmp/rd_st_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_st_err_$$
  rm -rf "$GNUPGHOME_TMP"
  echo "{\"success\":false,\"message\":\"Erro ao baixar/instalar a chave GPG da Ookla: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_st_err_$$
rm -rf "$GNUPGHOME_TMP"
chmod 644 "$CHAVEIRO"

# O repositorio da Ookla no packagecloud costuma ficar atras dos codinomes
# mais novos do Ubuntu (ex: sem "noble" ainda em 24.04) -- o pacote em si
# e um binario Go estatico, sem dependencia real da versao, entao usar o
# codinome LTS mais recente que a Ookla de fato publicou funciona em
# qualquer Ubuntu mais novo. So cai pro codinome real da maquina se ele
# tiver Release publicado (permite a Ookla passar a suportar codinomes
# novos sem precisar mudar este script).
CODINOME="$(. /etc/os-release && echo "$VERSION_CODENAME")"
CODINOME_FALLBACK="jammy"

if ! curl -fsS -o /dev/null "https://packagecloud.io/ookla/speedtest-cli/ubuntu/dists/${CODINOME}/Release"; then
  CODINOME="$CODINOME_FALLBACK"
fi

echo "deb [signed-by=${CHAVEIRO}] https://packagecloud.io/ookla/speedtest-cli/ubuntu/ ${CODINOME} main" \
  | tee "$REPO_ARQUIVO" >/dev/null

# "-qq" so silencia o progresso do proprio apt -- o dpkg (chamado por
# baixo) continua imprimindo "Unpacking.../Setting up..." no stdout mesmo
# em modo nao interativo, o que quebra o json_decode() do lado PHP (mesmo
# bug ja corrigido em antivirus_instalar_web.sh e dependencias_instalar_web.sh).
if ! apt-get update -qq >/tmp/rd_st_out_$$ 2>/tmp/rd_st_err_$$; then
  ERRO="$(tail -10 /tmp/rd_st_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_st_out_$$ /tmp/rd_st_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao atualizar lista de pacotes apos adicionar o repositorio da Ookla: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_st_out_$$ /tmp/rd_st_err_$$

if ! apt-get install -y -qq speedtest >/tmp/rd_st_out_$$ 2>/tmp/rd_st_err_$$; then
  ERRO="$(tail -20 /tmp/rd_st_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_st_out_$$ /tmp/rd_st_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar o Speedtest CLI: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_st_out_$$ /tmp/rd_st_err_$$

mkdir -p /var/lib/rd-intranet/speedtest
chown www-data:www-data /var/lib/rd-intranet/speedtest
chmod 700 /var/lib/rd-intranet/speedtest

echo '{"success":true,"message":"Speedtest CLI instalado com sucesso."}'
