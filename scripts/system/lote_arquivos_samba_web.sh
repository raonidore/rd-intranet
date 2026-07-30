#!/bin/bash
# lote_arquivos_samba_web.sh <execucao_id> <acao> <dest_dir_rel> <path1> [<path2> ...]
#
# acao: excluir | mover | copiar (dest_dir_rel vazio quando acao=excluir)
#
# Excluir/mover/copiar centenas de itens um a um via sudo + processo novo
# por item (como os endpoints de item unico fazem, cada um com seu proprio
# sudo+bash+python3) e caro demais pra uma unica requisicao HTTP aguentar
# -- foi o que aconteceu tentando excluir 1000 arquivos de uma vez (o PHP/
# Apache cortou a requisicao no meio, deixando o lote pela metade sem o
# usuario saber quanto tinha avancado). Este script roda em segundo plano
# (LinuxService::executarScriptEmSegundoPlano) e faz TUDO num unico
# processo bash (sem sudo/processo novo por item), escrevendo o proprio
# progresso, que o portal consulta por polling -- mesmo esquema ja usado
# em aplicar_acl_compartilhamento_web.sh e backup_executar_web.sh.
#
# Mesma validacao (realpath + startswith) de excluir/mover/copiar_arquivo_
# samba_web.sh, replicada aqui por item -- inclusive a trava de nao deixar
# excluir a raiz de um compartilhamento. Falha num item nao aborta os
# demais; os erros ficam listados na mensagem final.

STATUS_DIR="/var/www/rd.intranet/storage/samba_lote_status"
mkdir -p "$STATUS_DIR"

BASE="/srv/samba/Compartilhamentos"
BASE_DEPTH=$(echo "$BASE" | tr -cd '/' | wc -c)

EXECUCAO_ID="$1"
ACAO="$2"
DEST_DIR_REL="$3"
shift 3

STATUS_FILE="$STATUS_DIR/${EXECUCAO_ID}.json"

escrever_status() {
  local status="$1" processados="$2" total="$3" mensagem="${4:-}"
  php -r '
    $status = $argv[1];
    $processados = (int)$argv[2];
    $total = (int)$argv[3];
    $mensagem = $argv[4];
    $pct = $total > 0 ? (int)round(($processados / $total) * 100) : 100;
    echo json_encode([
        "status" => $status,
        "processados" => $processados,
        "total" => $total,
        "percentual" => min(100, max(0, $pct)),
        "mensagem" => $mensagem,
        "atualizado_em" => time(),
    ]);
  ' -- "$status" "$processados" "$total" "$mensagem" > "$STATUS_FILE"
  chmod 644 "$STATUS_FILE"
}

if [[ ! "$EXECUCAO_ID" =~ ^[a-f0-9]+$ ]]; then
  echo "ID de execucao invalido" >&2
  exit 1
fi

if [[ "$ACAO" != "excluir" && "$ACAO" != "mover" && "$ACAO" != "copiar" ]]; then
  escrever_status "erro" 0 0 "Acao invalida."
  exit 1
fi

REAL_DEST=""
if [ "$ACAO" != "excluir" ]; then
  if echo "$DEST_DIR_REL" | grep -q '\.\.'; then
    escrever_status "erro" 0 0 "Pasta de destino invalida."
    exit 1
  fi
  # caminho via sys.argv, nao interpolado na string -- ver comentario
  # abaixo, no loop principal (apostrofo no nome quebrava a string Python)
  REAL_DEST=$(python3 -c "import os,sys; p=os.path.realpath(sys.argv[1]); print(p if p.startswith(sys.argv[2]) else '')" "$BASE/$DEST_DIR_REL" "$BASE" 2>/dev/null)
  if [ -z "$REAL_DEST" ] || [ ! -d "$REAL_DEST" ]; then
    escrever_status "erro" 0 0 "Pasta de destino nao encontrada."
    exit 1
  fi
fi

TOTAL=$#
PROCESSADOS=0
ERROS=()

escrever_status "rodando" 0 "$TOTAL" ""

for REL in "$@"; do
  PROCESSADOS=$((PROCESSADOS + 1))

  if echo "$REL" | grep -q '\.\.'; then
    ERROS+=("$(basename "$REL"): caminho invalido")
    escrever_status "rodando" "$PROCESSADOS" "$TOTAL" ""
    continue
  fi

  # "$REL" via sys.argv, NUNCA interpolado direto na string de codigo --
  # um nome de arquivo com apostrofo (comum em portugues, ex: "d'oce")
  # interpolado quebra a string Python no meio, o realpath falha
  # silenciosamente (2>/dev/null escondia o erro) e reporta "nao
  # encontrado" pra um arquivo que existe de verdade. Foi exatamente isso
  # que aconteceu na primeira vez que este script rodou em producao.
  REAL=$(python3 -c "import os,sys; p=os.path.realpath(sys.argv[1]); print(p if p.startswith(sys.argv[2]) else '')" "$BASE/$REL" "$BASE" 2>/dev/null)
  if [ -z "$REAL" ] || [ ! -e "$REAL" ]; then
    ERROS+=("$(basename "$REL"): nao encontrado")
    escrever_status "rodando" "$PROCESSADOS" "$TOTAL" ""
    continue
  fi

  case "$ACAO" in
    excluir)
      REAL_DEPTH=$(echo "$REAL" | tr -cd '/' | wc -c)
      if [ "$REAL_DEPTH" -le $((BASE_DEPTH + 1)) ]; then
        ERROS+=("$(basename "$REAL"): nao e permitido excluir compartilhamentos raiz")
      elif ! rm -rf "$REAL" 2>/dev/null; then
        ERROS+=("$(basename "$REAL"): falha ao excluir")
      fi
      ;;
    mover)
      DEST_FILE="$REAL_DEST/$(basename "$REAL")"
      if [[ "$REAL_DEST" == "$REAL" || "$REAL_DEST" == "$REAL/"* ]]; then
        ERROS+=("$(basename "$REAL"): nao e possivel mover para dentro da propria pasta")
      elif [ -e "$DEST_FILE" ]; then
        ERROS+=("$(basename "$REAL"): ja existe um item com este nome no destino")
      elif ! mv "$REAL" "$REAL_DEST/" 2>/dev/null; then
        ERROS+=("$(basename "$REAL"): falha ao mover")
      fi
      ;;
    copiar)
      DEST_FILE="$REAL_DEST/$(basename "$REAL")"
      if [ -e "$DEST_FILE" ]; then
        ERROS+=("$(basename "$REAL"): ja existe um item com este nome no destino")
      elif ! cp -r "$REAL" "$REAL_DEST/" 2>/dev/null; then
        ERROS+=("$(basename "$REAL"): falha ao copiar")
      fi
      ;;
  esac

  escrever_status "rodando" "$PROCESSADOS" "$TOTAL" ""
done

if [ "${#ERROS[@]}" -gt 0 ]; then
  MENSAGEM=$(IFS='; '; echo "${ERROS[*]}")
  escrever_status "erro" "$PROCESSADOS" "$TOTAL" "$MENSAGEM"
  exit 1
fi

escrever_status "concluido" "$TOTAL" "$TOTAL" ""
exit 0
