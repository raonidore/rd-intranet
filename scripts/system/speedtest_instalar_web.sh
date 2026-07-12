#!/bin/bash
# speedtest_instalar_web.sh
# Adiciona o repositorio oficial da Ookla (packagecloud) e instala o
# Speedtest CLI. Feito de forma explicita/auditavel (baixa a chave GPG e
# escreve o sources.list.d na mao) -- de proposito NAO usa
# "curl | sudo bash" (nenhum outro script deste repo instala as cegas via
# pipe). URLs/formato conferidos ao vivo antes de escrever este script:
#   curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/config_file.list?os=ubuntu&dist=noble&source=script
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

if ! curl -fsSL "https://packagecloud.io/ookla/speedtest-cli/gpgkey" 2>/tmp/rd_st_err_$$ | gpg --dearmor -o "$CHAVEIRO" 2>>/tmp/rd_st_err_$$; then
  ERRO="$(tail -10 /tmp/rd_st_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_st_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao baixar/instalar a chave GPG da Ookla: ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_st_err_$$
chmod 644 "$CHAVEIRO"

CODINOME="$(. /etc/os-release && echo "$VERSION_CODENAME")"

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
