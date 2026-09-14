#!/bin/bash
# backup_tamanho_web.sh <remote> <destino_remoto>
#
# Soma o tamanho (bytes) e a quantidade de arquivos que estao HOJE no
# destino de backup (tela Backup > Configuracao, coluna "Tamanho do
# backup") -- inclui tudo dentro do destino, cópia atual e .versoes/.
# Roda `rclone size --json` contra o rclone.conf ja persistido (destino
# ja salvo, ao contrario do backup_testar_conexao_web.sh que testa uma
# credencial ainda nao salva). Funciona pra qualquer backend porque soma
# via listagem (nao depende do provedor suportar "rclone about"), mas por
# isso mesmo pode demorar alguns segundos num destino com muitos arquivos
# -- por isso e chamado sob demanda (botao/AJAX), nunca no carregamento
# da pagina.

set -u

REMOTE="$1"
DESTINO_REMOTO="$2"
CONFIG="/etc/rd-intranet/rclone/rclone.conf"

if [[ ! "$REMOTE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo '{"success":false,"message":"Nome de destino invalido."}'
  exit 1
fi

if [ ! -f "$CONFIG" ]; then
  echo '{"success":false,"message":"Configuracao de backup ainda nao foi aplicada."}'
  exit 1
fi

if [ -n "$DESTINO_REMOTO" ]; then
  ALVO="${REMOTE}:${DESTINO_REMOTO}"
else
  ALVO="${REMOTE}:"
fi

ERR_FILE=$(mktemp)
SAIDA=$(timeout 90 rclone size --json --config "$CONFIG" "$ALVO" 2>"$ERR_FILE")
CODIGO=$?
ERRO=$(tail -5 "$ERR_FILE")
rm -f "$ERR_FILE"

if [ "$CODIGO" -eq 0 ] && [ -n "$SAIDA" ]; then
  php -r '
    $dados = json_decode($argv[1], true);
    if (!is_array($dados)) {
        echo json_encode(["success" => false, "message" => "Resposta inesperada do rclone."]);
        exit;
    }
    echo json_encode([
        "success" => true,
        "bytes" => (int)($dados["bytes"] ?? 0),
        "arquivos" => (int)($dados["count"] ?? 0),
    ]);
  ' -- "$SAIDA"
else
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao calcular o tamanho: " . $argv[1]]);' -- "$ERRO"
fi
