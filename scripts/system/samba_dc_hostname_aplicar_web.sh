#!/bin/bash
# samba_dc_hostname_aplicar_web.sh <novo_hostname>
#
# Troca o hostname da maquina -- pre-requisito real pro provisionamento de
# Samba AD DC (samba-tool deriva o nome do DC do hostname curto). Nao existia
# nenhuma UI pra isso antes; mantido minimo de proposito (so hostnamectl +
# ajuste da linha 127.0.1.1 de /etc/hosts, se existir -- nada alem disso).

set -u

NOVO="$1"

if [[ ! "$NOVO" =~ ^[a-z][a-z0-9-]{0,14}$ ]]; then
  echo '{"success":false,"message":"Hostname invalido. Use letras minusculas, numeros e \"-\", comecando com letra, ate 15 caracteres (limite do NetBIOS)."}'
  exit 1
fi

ANTIGO=$(hostname)

if ! hostnamectl set-hostname "$NOVO" 2>/tmp/rd_hostname_err_$$; then
  ERRO=$(tail -5 /tmp/rd_hostname_err_$$ 2>/dev/null)
  rm -f /tmp/rd_hostname_err_$$
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao trocar hostname: " . $argv[1]]);' -- "$ERRO"
  exit 1
fi
rm -f /tmp/rd_hostname_err_$$

if grep -qE '^127\.0\.1\.1[[:space:]]' /etc/hosts 2>/dev/null; then
  sed -i "s/^127\.0\.1\.1[[:space:]].*/127.0.1.1\t${NOVO}/" /etc/hosts
fi

php -r 'echo json_encode(["success" => true, "message" => "Hostname alterado de \"" . $argv[1] . "\" para \"" . $argv[2] . "\". Pode ser necessario reabrir sessoes SSH."]);' -- "$ANTIGO" "$NOVO"
