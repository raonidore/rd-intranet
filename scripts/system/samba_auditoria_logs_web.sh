#!/bin/bash
# samba_auditoria_logs_web.sh
# Somente leitura: ultimas entradas do log da Auditoria de Arquivos
# (Samba > Auditoria). Bounded (nao le o arquivo inteiro) -- histórico
# maior fica pro proprio arquivo em disco, rotacionado por
# setup_samba_auditoria.sh.
#
# Precisa de root porque /var/log/samba/audit.log nasce dono syslog:adm
# 640 (mesmo esquema de /var/log/auth.log) -- www-data nao tem acesso
# direto de proposito, mesmo motivo de qualquer outro log do sistema
# nesta aplicacao.

set -u

ARQUIVO="/var/log/samba/audit.log"

if [ ! -f "$ARQUIVO" ]; then
  exit 0
fi

tail -n 5000 "$ARQUIVO" 2>/dev/null
