#!/bin/bash
# setup_db_secret_key.sh
# Passo de instalacao (rodar uma vez, como root: sudo ./setup_db_secret_key.sh).
# NAO tem sufixo _web.sh de proposito -- fora da regra de sudo NOPASSWD do
# www-data, mesmo criterio ja usado em setup_rotas_extras.sh.
#
# Gera a chave de criptografia (AES-256-GCM) usada pelo CryptoService pra
# guardar senhas de conexoes de banco de dados de clientes. Idempotente: se
# a chave ja existir, nao mexe nela (perderia acesso as senhas ja salvas).

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

mkdir -p /etc/rd-intranet

if [ -f /etc/rd-intranet/db_secret.key ]; then
  echo "Chave ja existe, mantendo."
else
  openssl rand -base64 32 > /etc/rd-intranet/db_secret.key
  echo "Chave gerada."
fi

chown root:www-data /etc/rd-intranet/db_secret.key
chmod 640 /etc/rd-intranet/db_secret.key

echo "OK"
