#!/bin/bash
# backup_executar_web.sh <execucao_id> <remote> <destino_remoto> <retencao_dias> <nome1> <caminho1> [<nome2> <caminho2> ...]
#
# Espelha, um de cada vez, cada compartilhamento do Samba marcado com
# "Backup nuvem" (Samba > Compartilhamentos, coluna backup_nuvem_ativo)
# pro <remote> (secao do /etc/rd-intranet/rclone/rclone.conf gerada por
# backup_aplicar_config_web.sh), usando `rclone sync --backup-dir`: em vez
# de um mirror destrutivo (que apagaria/sobrescreveria na nuvem qualquer
# arquivo apagado ou corrompido localmente -- exatamente o cenario que o
# cliente teme, ex. ransomware ou exclusao acidental), qualquer arquivo
# que seria sobrescrito/removido no destino e primeiro movido pra uma
# pasta datada em .versoes/ -- nada se perde, so acumula ate a limpeza por
# retencao no fim de cada compartilhamento.
#
# Cada compartilhamento ganha sua propria subpasta dentro do destino
# (<remote>:<destino_remoto>/<nome_compartilhamento>/), pra nao misturar
# arvores de compartilhamentos diferentes no mesmo bucket/prefixo.
#
# Chamado em segundo plano (LinuxService::executarScriptEmSegundoPlano) --
# pode levar minutos numa arvore grande -- e escreve o proprio progresso
# num UNICO arquivo de status (acumulado entre todos os compartilhamentos
# da execucao) que o portal consulta por polling (mesmo esquema de
# aplicar_acl_compartilhamento_web.sh), so que aqui o progresso e REAL:
# rclone com --use-json-log --stats 5s imprime bytes/total a cada tick no
# log, este script tail-a e reescreve o status.
#
# Falha num compartilhamento NAO aborta os seguintes -- a execucao so
# termina como "erro" se pelo menos um falhar, com a lista de quais no
# campo "mensagem", mas todos os outros ainda sao sincronizados.

STATUS_DIR="/var/www/rd.intranet/storage/backup_status"
mkdir -p "$STATUS_DIR"

EXECUCAO_ID="$1"
REMOTE="$2"
DESTINO_REMOTO="$3"
RETENCAO_DIAS="${4:-30}"
shift 4

STATUS_FILE="$STATUS_DIR/${EXECUCAO_ID}.json"
# um registro por linha (JSON Lines) de cada arquivo novo/atualizado/excluido
# desta execucao, em TODOS os compartilhamentos -- BackupService le este
# arquivo ao detectar o status final e persiste em backup_execucao_arquivos
# (Backup > Historico, botao "Ver arquivos").
ARQUIVOS_JSONL="$STATUS_DIR/${EXECUCAO_ID}.arquivos.jsonl"
: > "$ARQUIVOS_JSONL"
chmod 644 "$ARQUIVOS_JSONL"
CONFIG="/etc/rd-intranet/rclone/rclone.conf"

escrever_status() {
  local status="$1" bytes="$2" total_bytes="$3" arquivos="$4" mensagem="${5:-}" versoes="${6:-0}" velocidade="${7:-0}" eta="${8:-0}"
  php -r '
    $status = $argv[1];
    $bytes = (int)$argv[2];
    $totalBytes = (int)$argv[3];
    $arquivos = (int)$argv[4];
    $mensagem = $argv[5];
    $versoes = (int)$argv[6];
    $velocidade = (int)$argv[7];
    $eta = (int)$argv[8];
    $pct = $totalBytes > 0 ? (int)round(($bytes / $totalBytes) * 100) : ($status === "concluida" ? 100 : 0);
    echo json_encode([
        "status" => $status,
        "bytes_enviados" => $bytes,
        "bytes_totais" => $totalBytes,
        "arquivos_enviados" => $arquivos,
        "versoes_criadas" => $versoes,
        "percentual" => min(100, max(0, $pct)),
        "velocidade_bytes_seg" => $velocidade,
        "eta_segundos" => $eta,
        "mensagem" => $mensagem,
        "atualizado_em" => time(),
    ]);
  ' -- "$status" "$bytes" "$total_bytes" "$arquivos" "$mensagem" "$versoes" "$velocidade" "$eta" > "$STATUS_FILE"
  chmod 644 "$STATUS_FILE"
}

ultima_linha_stats() {
  # ultima linha do log que tem um objeto "stats" (rclone --use-json-log),
  # vazio se o rclone ainda nao imprimiu nenhum tick
  grep -F '"stats":' "$1" 2>/dev/null | tail -1
}

campo_stats() {
  # campo_stats <linha_json> <nome_campo>
  php -r 'echo (int)(json_decode($argv[1], true)["stats"][$argv[2]] ?? 0);' -- "$1" "$2"
}

mensagem_erro_rclone() {
  # ultima linha "level":"error" do log (--use-json-log), so o campo
  # "msg" (a mensagem humana do rclone) -- sem isso, so tinhamos o codigo
  # de saida (ex: "codigo 7"), o que nao dizia NADA sobre a causa real
  # (permissao negada, bucket errado, credencial invalida, timeout etc).
  local log_file="$1"
  local linha
  linha="$(grep -F '"level":"error"' "$log_file" 2>/dev/null | tail -1)"
  if [ -z "$linha" ]; then
    return 1
  fi
  php -r '
    $msg = trim((string)(json_decode($argv[1], true)["msg"] ?? ""));
    if ($msg !== "") { echo $msg; exit(0); }
    exit(1);
  ' -- "$linha"
}

if [[ ! "$EXECUCAO_ID" =~ ^[0-9]+$ ]]; then
  echo "ID de execucao invalido" >&2
  escrever_status "erro" 0 0 0 "ID de execucao invalido."
  exit 1
fi

if [[ ! "$REMOTE" =~ ^[a-zA-Z0-9_-]+$ ]]; then
  echo "Remote invalido" >&2
  escrever_status "erro" 0 0 0 "Nome de destino de backup invalido."
  exit 1
fi

if [ ! -f "$CONFIG" ]; then
  echo "rclone.conf nao encontrado -- salve a configuracao do destino antes de rodar o backup." >&2
  escrever_status "erro" 0 0 0 "Configuracao de backup ainda nao foi aplicada."
  exit 1
fi

if [ "$#" -eq 0 ] || [ $(( $# % 2 )) -ne 0 ]; then
  echo "Lista de compartilhamentos invalida" >&2
  escrever_status "erro" 0 0 0 "Nenhum compartilhamento valido foi informado."
  exit 1
fi

if [ -n "$DESTINO_REMOTO" ]; then
  ALVO_BASE="${REMOTE}:${DESTINO_REMOTO}"
else
  ALVO_BASE="${REMOTE}:"
fi

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
CORTE=$(date -d "-${RETENCAO_DIAS} days" +%Y%m%d_%H%M%S 2>/dev/null || echo "00000000_000000")

BYTES_ACUM=0
BYTES_TOTAIS_ACUM=0
ARQUIVOS_ACUM=0
VERSOES_ACUM=0
ERROS=()

escrever_status "rodando" 0 0 0 ""

while [ "$#" -ge 2 ]; do
  NOME="$1"
  CAMINHO="$2"
  shift 2

  if [[ ! "$NOME" =~ ^[A-Za-z0-9_-]+$ ]]; then
    ERROS+=("${NOME}: nome de compartilhamento invalido")
    continue
  fi

  if [ ! -d "$CAMINHO" ]; then
    ERROS+=("${NOME}: caminho nao encontrado no servidor (${CAMINHO})")
    continue
  fi

  ALVO_SHARE="${ALVO_BASE%/}/${NOME}"
  # .versoes fica IRMA de $NOME (nao dentro) -- rclone recusa --backup-dir
  # aninhado dentro do proprio destino do sync ("destination and parameter
  # to --backup-dir mustn't overlap"), entao a pasta versionada de cada
  # compartilhamento mora em ${ALVO_BASE}/.versoes/${NOME}/<timestamp>.
  PASTA_VERSAO="${ALVO_BASE%/}/.versoes/${NOME}/${TIMESTAMP}"
  LOG_FILE="$STATUS_DIR/${EXECUCAO_ID}.${NOME}.rclone.log"

  # cria o arquivo com permissao de leitura ANTES do rclone escrever nele
  # (rclone nao reseta a permissao de um arquivo ja existente) -- sem
  # isso o log nascia 600 root:root, ilegivel por qualquer um sem sudo,
  # inclusive pra diagnosticar uma falha depois
  touch "$LOG_FILE"
  chmod 644 "$LOG_FILE"

  rclone sync "$CAMINHO" "$ALVO_SHARE" \
    --config "$CONFIG" \
    --backup-dir "$PASTA_VERSAO" \
    --exclude ".recycle/**" \
    --use-json-log --stats-log-level NOTICE --stats 5s -v \
    --log-file "$LOG_FILE" &
  PID_RCLONE=$!

  while kill -0 "$PID_RCLONE" 2>/dev/null; do
    LINHA="$(ultima_linha_stats "$LOG_FILE")"
    if [ -n "$LINHA" ]; then
      BYTES_ATUAL=$(campo_stats "$LINHA" bytes)
      TOTAL_ATUAL=$(campo_stats "$LINHA" totalBytes)
      ARQ_ATUAL=$(campo_stats "$LINHA" transfers)
      VELOCIDADE_ATUAL=$(campo_stats "$LINHA" speed)
      ETA_ATUAL=$(campo_stats "$LINHA" eta)
      escrever_status "rodando" \
        "$((BYTES_ACUM + BYTES_ATUAL))" \
        "$((BYTES_TOTAIS_ACUM + TOTAL_ATUAL))" \
        "$((ARQUIVOS_ACUM + ARQ_ATUAL))" \
        "Sincronizando \"${NOME}\"..." \
        "0" \
        "$VELOCIDADE_ATUAL" \
        "$ETA_ATUAL"
    fi
    sleep 3
  done

  wait "$PID_RCLONE"
  CODIGO=$?

  LINHA_FINAL="$(ultima_linha_stats "$LOG_FILE")"
  # NAO usar "${LINHA_FINAL:-{}}" -- bash interpreta mal chaves dentro do
  # valor padrao de ':-' e devolve "{}}" (com chave sobrando) quando
  # LINHA_FINAL ja esta preenchida, quebrando o json_decode() silenciosamente
  [ -z "$LINHA_FINAL" ] && LINHA_FINAL='{}'
  BYTES_FINAL=$(campo_stats "$LINHA_FINAL" bytes)
  ARQ_FINAL=$(campo_stats "$LINHA_FINAL" transfers)

  BYTES_ACUM=$((BYTES_ACUM + BYTES_FINAL))
  BYTES_TOTAIS_ACUM=$((BYTES_TOTAIS_ACUM + BYTES_FINAL))
  ARQUIVOS_ACUM=$((ARQUIVOS_ACUM + ARQ_FINAL))

  if [ "$CODIGO" -ne 0 ]; then
    MOTIVO="$(mensagem_erro_rclone "$LOG_FILE")"
    if [ -z "$MOTIVO" ]; then
      MOTIVO="rclone terminou com erro (codigo ${CODIGO})"
    fi
    ERROS+=("${NOME}: ${MOTIVO}")
    continue
  fi

  # Detalhe por arquivo desta sincronizacao (Backup > Historico, "Ver
  # arquivos"): qualquer item que apareceu em PASTA_VERSAO tinha uma
  # versao anterior movida pra la pelo --backup-dir (prova fisica de que
  # existia antes, com o tamanho antigo certo -- nao depende de nenhuma
  # mensagem de log) -- se ainda existe localmente foi "atualizado", senao
  # foi "excluido". Arquivos "Copied (new)" no log que NAO passaram por
  # PASTA_VERSAO sao genuinamente novos. rclone lsjson funciona igual pra
  # qualquer backend (local/B2/S3/Drive), entao PASTA_VERSAO (um caminho
  # remoto tipo "destino-1:bucket/prefixo/.versoes/nome/timestamp") e lido
  # do jeito certo, nao como pasta local.
  VERSOES_JSON_FILE="$STATUS_DIR/${EXECUCAO_ID}.${NOME}.versoes.json"
  rclone lsjson -R --files-only --config "$CONFIG" "$PASTA_VERSAO" > "$VERSOES_JSON_FILE" 2>/dev/null

  VERSOES_SHARE=$(python3 - "$NOME" "$CAMINHO" "$VERSOES_JSON_FILE" "$LOG_FILE" "$ARQUIVOS_JSONL" << 'PYEOF'
import json, os, sys

nome, caminho, versoes_json_file, log_file, saida_file = sys.argv[1:6]

vistos = set()
linhas_saida = []

try:
    with open(versoes_json_file) as f:
        itens = json.load(f)
except (OSError, ValueError):
    itens = []

for item in itens:
    rel = item.get('Path')
    if not rel:
        continue
    vistos.add(rel)
    tam_anterior = item.get('Size')

    caminho_local = os.path.join(caminho, rel)
    if os.path.exists(caminho_local):
        try:
            tam_novo = os.path.getsize(caminho_local)
        except OSError:
            tam_novo = None
        tipo = 'atualizado'
    else:
        tam_novo = None
        tipo = 'excluido'

    linhas_saida.append({
        'compartilhamento': nome, 'caminho': rel, 'tipo': tipo,
        'tamanho_anterior': tam_anterior, 'tamanho_novo': tam_novo,
    })

if os.path.isfile(log_file):
    with open(log_file, errors='replace') as f:
        for linha in f:
            try:
                d = json.loads(linha)
            except ValueError:
                continue
            if d.get('msg') == 'Copied (new)' and 'object' in d:
                rel = d['object']
                if rel in vistos:
                    continue
                vistos.add(rel)
                caminho_local = os.path.join(caminho, rel)
                try:
                    tamanho = os.path.getsize(caminho_local)
                except OSError:
                    tamanho = None
                linhas_saida.append({
                    'compartilhamento': nome, 'caminho': rel, 'tipo': 'novo',
                    'tamanho_anterior': None, 'tamanho_novo': tamanho,
                })

with open(saida_file, 'a') as out:
    for item in linhas_saida:
        out.write(json.dumps(item) + '\n')

print(len(itens))
PYEOF
)
  rm -f "$VERSOES_JSON_FILE"
  VERSOES_ACUM=$((VERSOES_ACUM + VERSOES_SHARE))

  # retencao: expurga pastas de .versoes/ deste compartilhamento mais
  # antigas que RETENCAO_DIAS
  PASTA_VERSOES_RAIZ="${ALVO_BASE%/}/.versoes/${NOME}"
  rclone lsf --dirs-only --config "$CONFIG" "$PASTA_VERSOES_RAIZ" 2>/dev/null | while read -r PASTA; do
    NOME_PASTA="${PASTA%/}"
    if [[ "$NOME_PASTA" =~ ^[0-9]{8}_[0-9]{6}$ ]] && [[ "$NOME_PASTA" < "$CORTE" ]]; then
      rclone purge --config "$CONFIG" "${PASTA_VERSOES_RAIZ}/${NOME_PASTA}" >/dev/null 2>&1
    fi
  done
done

if [ "${#ERROS[@]}" -gt 0 ]; then
  MENSAGEM=$(IFS='; '; echo "${ERROS[*]}")
  escrever_status "erro" "$BYTES_ACUM" "$BYTES_TOTAIS_ACUM" "$ARQUIVOS_ACUM" "$MENSAGEM" "$VERSOES_ACUM"
  exit 1
fi

escrever_status "concluida" "$BYTES_ACUM" "$BYTES_TOTAIS_ACUM" "$ARQUIVOS_ACUM" "" "$VERSOES_ACUM"
exit 0
