#!/bin/bash
# Substitui, de vez, a necessidade de liberar cada script novo
# manualmente (como scripts/grant-sudo-atualizacao.sh fez pro modulo de
# Atualizacoes) por uma unica regra coringa que cobre qualquer script
# atual OU futuro em /opt/rdtecnologia/scripts/*.sh. Roda uma vez,
# manualmente, como root:
#
#   sudo /var/www/rd.intranet/scripts/grant-sudo-wildcard.sh
#
# Seguro: www-data nao tem escrita em /opt/rdtecnologia/scripts (dono
# root, sincronizado so por sync-system-scripts.sh, que tambem roda como
# root), entao a regra coringa nao abre brecha pra www-data colocar um
# script arbitrario ali e rodar como root -- so os scripts que o proprio
# deploy (dono do repositorio) publicou.
#
# So ACRESCENTA uma regra ao /etc/sudoers.d/rd-intranet existente (nao
# reescreve o arquivo inteiro, nao remove as regras antigas enumeradas --
# ficam redundantes mas inofensivas), e valida com visudo antes de
# aplicar. Idempotente: rodar de novo nao duplica a linha.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

ARQUIVO="/etc/sudoers.d/rd-intranet"
MARCADOR="# rd-intranet: coringa para /opt/rdtecnologia/scripts/*.sh"
REGRA="www-data ALL=(root) NOPASSWD: /opt/rdtecnologia/scripts/*.sh"

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

echo "OK: qualquer script em /opt/rdtecnologia/scripts/*.sh agora esta liberado pra www-data."
echo "Scripts novos publicados dai em diante nao precisam mais de grant manual."
