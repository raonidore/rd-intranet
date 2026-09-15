#!/bin/bash
# samba_auditoria_retencao_web.sh [dias]
#
# Sem argumento: devolve um JSON com o estado atual da retencao do log
# de Auditoria de Arquivos (/var/log/samba/audit.log) -- dias
# configurados, tamanho atual do arquivo (soma o ativo + os .gz
# rotacionados, que e o espaco de verdade ocupado pelo historico
# completo) e a data do arquivo rotacionado mais antigo ainda presente
# (aproxima ha quanto tempo o historico realmente remonta, na pratica,
# sem depender só do numero configurado -- logrotate só roda 1x/dia,
# entao um log que nunca cresce o suficiente pode "durar" mais que o
# configurado, e um log que enche rápido pode não durar os dias
# inteiros se o disco genérico do logrotate não intervier -- não é o
# caso aqui, mas o numero de "dias configurados" sozinho não prova
# nada sobre o que está de fato em disco agora).
#
# Com argumento numerico: reescreve /etc/logrotate.d/samba-audit com
# "rotate <dias>" e devolve o mesmo JSON de status atualizado.
#
# O arquivo de logrotate é gerado por setup_samba_auditoria.sh com um
# valor padrao (30) na primeira instalacao -- depois disso, ESTE
# script é o unico autorizado a reescrever o "rotate N", pra nao
# apagar uma escolha do admin a cada "Aplicar atualizacao" (ver
# comentario em setup_samba_auditoria.sh).

set -u

ARQUIVO_LOG="/var/log/samba/audit.log"
ARQUIVO_LOGROTATE="/etc/logrotate.d/samba-audit"
DIAS="${1:-}"

if [ -n "$DIAS" ]; then
  if ! [[ "$DIAS" =~ ^[0-9]+$ ]] || [ "$DIAS" -lt 1 ] || [ "$DIAS" -gt 3650 ]; then
    echo '{"success":false,"message":"Numero de dias invalido (use entre 1 e 3650)."}'
    exit 1
  fi

  if [ ! -f "$ARQUIVO_LOGROTATE" ]; then
    echo '{"success":false,"message":"Configuracao de logrotate nao encontrada -- rode a atualizacao do sistema (setup_samba_auditoria) primeiro."}'
    exit 1
  fi

  cp "$ARQUIVO_LOGROTATE" "${ARQUIVO_LOGROTATE}.bak"
  sed -i -E "s/^([[:space:]]*)rotate [0-9]+$/\1rotate ${DIAS}/" "$ARQUIVO_LOGROTATE"

  if ! grep -qE "^[[:space:]]*rotate ${DIAS}$" "$ARQUIVO_LOGROTATE"; then
    mv "${ARQUIVO_LOGROTATE}.bak" "$ARQUIVO_LOGROTATE"
    echo '{"success":false,"message":"Falha ao gravar a nova retencao, configuracao revertida."}'
    exit 1
  fi

  rm -f "${ARQUIVO_LOGROTATE}.bak"
fi

DIAS_ATUAL=$(grep -oE "rotate [0-9]+" "$ARQUIVO_LOGROTATE" 2>/dev/null | grep -oE "[0-9]+" || echo "")
TAMANHO_BYTES=$(du -cb "${ARQUIVO_LOG}" "${ARQUIVO_LOG}".*.gz 2>/dev/null | tail -1 | cut -f1)
TAMANHO_BYTES="${TAMANHO_BYTES:-0}"
ARQUIVO_MAIS_ANTIGO=$(ls -t "${ARQUIVO_LOG}".*.gz 2>/dev/null | tail -1)
DATA_MAIS_ANTIGA=""
if [ -n "$ARQUIVO_MAIS_ANTIGO" ]; then
  DATA_MAIS_ANTIGA=$(date -r "$ARQUIVO_MAIS_ANTIGO" +%Y-%m-%d 2>/dev/null || echo "")
fi

printf '{"success":true,"dias":%s,"tamanho_bytes":%s,"data_mais_antiga":"%s"}\n' \
  "${DIAS_ATUAL:-null}" "${TAMANHO_BYTES}" "${DATA_MAIS_ANTIGA}"
