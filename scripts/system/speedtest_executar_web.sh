#!/bin/bash
# speedtest_executar_web.sh
# Roda o teste de velocidade (Ookla Speedtest CLI) e imprime o JSON cru
# da ferramenta em stdout. NAO precisa de root -- o binario so precisa de
# rede -- por isso e chamado via LinuxService::executar() (sem sudo), nao
# executarScript(). HOME aponta pro diretorio criado (dono www-data) por
# speedtest_instalar_web.sh, senao a home inexistente do www-data pode
# quebrar a gravacao da aceitacao de licenca na primeira execucao.

set -u

if ! command -v speedtest >/dev/null 2>&1; then
  echo '{"success":false,"message":"Speedtest CLI nao esta instalado."}'
  exit 1
fi

SAIDA="$(HOME=/var/lib/rd-intranet/speedtest speedtest --accept-license --accept-gdpr -f json 2>&1)"
STATUS=$?

if [ $STATUS -ne 0 ]; then
  ERRO="$(echo "$SAIDA" | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"Erro ao executar o teste: ${ERRO}\"}"
  exit 1
fi

echo "$SAIDA"
