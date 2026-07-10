#!/bin/bash
# Libera para www-data, via sudo sem senha, os 3 scripts do modulo
# Atualizacoes (Administracao > Atualizacoes). Roda uma vez, manualmente,
# como root:
#
#   sudo /var/www/rd.intranet/scripts/grant-sudo-atualizacao.sh
#
# Necessario apenas em servidores que ja existiam antes deste modulo (o
# sudoers deles enumera script por script). Servidores novos instalados via
# scripts/install.sh ja saem com uma regra coringa que cobre isso -- rodar
# este script neles nao faz mal (fica idempotente), so nao e necessario.
#
# So ACRESCENTA uma regra ao /etc/sudoers.d/rd-intranet existente (nao
# reescreve o arquivo inteiro), e valida com visudo antes de aplicar.
# Idempotente: rodar de novo nao duplica a linha.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

ARQUIVO="/etc/sudoers.d/rd-intranet"
MARCADOR="# rd-intranet: modulo Atualizacoes"
REGRA="www-data ALL=(root) NOPASSWD: /opt/rdtecnologia/scripts/atualizar_verificar_web.sh, /opt/rdtecnologia/scripts/atualizar_aplicar_web.sh, /opt/rdtecnologia/scripts/atualizar_reverter_web.sh"

if [ ! -f "$ARQUIVO" ]; then
  echo "Nao encontrei $ARQUIVO -- crie o sudoers base da RD Intranet antes de rodar este script." >&2
  exit 1
fi

if grep -qF "$MARCADOR" "$ARQUIVO"; then
  echo "Ja aplicado, nada a fazer."
  exit 0
fi

TMP="$(mktemp)"
cp "$ARQUIVO" "$TMP"
{
  echo ""
  echo "$MARCADOR"
  echo "$REGRA"
} >> "$TMP"

if ! visudo -c -f "$TMP" >/dev/null; then
  echo "Regra gerada invalida, nada foi alterado. Confira manualmente." >&2
  rm -f "$TMP"
  exit 1
fi

cp "$TMP" "$ARQUIVO"
chown root:root "$ARQUIVO"
chmod 640 "$ARQUIVO"
rm -f "$TMP"

echo "OK: sudo liberado para os scripts do modulo Atualizacoes."
