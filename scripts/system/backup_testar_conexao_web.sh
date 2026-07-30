#!/bin/bash
# backup_testar_conexao_web.sh <arquivo_config_tmp> <remote> <destino_remoto>
#
# Testa uma credencial de backup ANTES de salvar (botao "Testar conexao"
# da tela Backup > Configuracao). <arquivo_config_tmp> e um rclone.conf
# minimo (so a secao do remote sendo testado) gerado pelo PHP via
# tempnam() -- nunca persistido, apagado pelo PHP logo depois desta
# chamada. Roda `rclone lsd`, rapido o bastante pra ficar sincrono (sem
# segundo plano/polling).

set -u

CONFIG="$1"
REMOTE="$2"
DESTINO_REMOTO="$3"

if [ ! -f "$CONFIG" ]; then
  echo '{"success":false,"message":"Arquivo de configuracao nao encontrado."}'
  exit 1
fi

if [[ ! "$REMOTE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo '{"success":false,"message":"Nome de destino invalido."}'
  exit 1
fi

if [ -n "$DESTINO_REMOTO" ]; then
  ALVO="${REMOTE}:${DESTINO_REMOTO}"
else
  ALVO="${REMOTE}:"
fi

SAIDA=$(timeout 30 rclone lsd --config "$CONFIG" "$ALVO" 2>&1)
CODIGO=$?

if [ "$CODIGO" -eq 0 ]; then
  echo '{"success":true,"message":"Conexao ok -- credenciais e bucket/pasta acessiveis."}'
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao conectar: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
fi
