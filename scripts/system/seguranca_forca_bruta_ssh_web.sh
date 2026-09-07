#!/bin/bash
# seguranca_forca_bruta_ssh_web.sh <execucao_id> <ip> <usuario>
#
# Testa a senha de UMA conta especifica (ja escolhida por quem operou a
# tela) num host SSH dentro da rede privada, contra uma wordlist curada
# de senhas comuns -- nao adivinha usuario tambem, de proposito: o
# escopo e "essa conta e fraca?", nao "descobrir contas validas". Roda
# em segundo plano (pode levar minutos com "-t 4 -W" respeitando o
# servico) e escreve o proprio progresso, mesmo contrato de
# escrever_status ja usado em ip_scanner_web.sh e
# samba_dc_provisionar_web.sh.
#
# Progresso real: "-V" do hydra imprime uma linha "[ATTEMPT] ... N of
# TOTAL" por tentativa -- o script conta essas linhas enquanto o hydra
# roda em background, sem esperar ele terminar pra reportar.

set -u

STATUS_DIR="/var/www/rd.intranet/storage/seguranca_forca_bruta_status"
mkdir -p "$STATUS_DIR"

EXECUCAO_ID="$1"
IP="$2"
USUARIO="$3"

STATUS_FILE="$STATUS_DIR/${EXECUCAO_ID}.json"

escrever_status() {
  local status="$1" percentual="$2" mensagem="$3" encontrado_json="${4:-null}"
  php -r '
    $status = $argv[1];
    $percentual = (int)$argv[2];
    $mensagem = $argv[3];
    $encontrado = json_decode($argv[4], true);
    echo json_encode([
        "status" => $status,
        "percentual" => min(100, max(0, $percentual)),
        "mensagem" => $mensagem,
        "encontrado" => $encontrado,
        "atualizado_em" => time(),
    ]);
  ' -- "$status" "$percentual" "$mensagem" "$encontrado_json" > "$STATUS_FILE"
  chmod 644 "$STATUS_FILE"
}

if [[ ! "$EXECUCAO_ID" =~ ^[a-f0-9]+$ ]]; then
  echo "ID de execucao invalido" >&2
  exit 1
fi

REGEX_IPV4='^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$'
if [[ ! "$IP" =~ $REGEX_IPV4 ]]; then
  escrever_status "erro" 0 "IP inválido."
  exit 1
fi

OCTETO1="${BASH_REMATCH[1]}"
OCTETO2="${BASH_REMATCH[2]}"

PRIVADO=0
if [ "$OCTETO1" -eq 10 ]; then
  PRIVADO=1
elif [ "$OCTETO1" -eq 172 ] && [ "$OCTETO2" -ge 16 ] && [ "$OCTETO2" -le 31 ]; then
  PRIVADO=1
elif [ "$OCTETO1" -eq 192 ] && [ "$OCTETO2" -eq 168 ]; then
  PRIVADO=1
elif [ "$OCTETO1" -eq 169 ] && [ "$OCTETO2" -eq 254 ]; then
  PRIVADO=1
fi

if [ "$PRIVADO" -ne 1 ]; then
  escrever_status "erro" 0 "Só é permitido testar hosts em faixa de rede privada (RFC1918) ou link-local."
  exit 1
fi

if [ -z "$USUARIO" ]; then
  escrever_status "erro" 0 "Informe a conta a testar."
  exit 1
fi

command -v hydra >/dev/null 2>&1 || { escrever_status "erro" 0 "hydra não está instalado. Instale em Infraestrutura > Dependências."; exit 1; }

WORDLIST="/var/www/rd.intranet/resources/wordlists/senhas_comuns.txt"
if [ ! -f "$WORDLIST" ]; then
  escrever_status "erro" 0 "Wordlist não encontrada no servidor."
  exit 1
fi

TOTAL=$(grep -c . "$WORDLIST")
[ "$TOTAL" -lt 1 ] && TOTAL=1

escrever_status "rodando" 0 "Testando \"${USUARIO}\" em ${IP} (0/${TOTAL})..."

SAIDA_TMP="/tmp/rd_ssh_bruteforce_${EXECUCAO_ID}.log"

hydra -l "$USUARIO" -P "$WORDLIST" -t 4 -f -V "$IP" ssh >"$SAIDA_TMP" 2>&1 &
HYDRA_PID=$!

while kill -0 "$HYDRA_PID" 2>/dev/null; do
  PROCESSADAS=$(grep -c '\[ATTEMPT\]' "$SAIDA_TMP" 2>/dev/null)
  [ -z "$PROCESSADAS" ] && PROCESSADAS=0
  PCT=$(( PROCESSADAS * 100 / TOTAL ))
  escrever_status "rodando" "$PCT" "Testando \"${USUARIO}\" em ${IP} (${PROCESSADAS}/${TOTAL})..."
  sleep 2
done

wait "$HYDRA_PID"

if grep -q 'login:' "$SAIDA_TMP"; then
  LINHA_ACHADA=$(grep 'login:' "$SAIDA_TMP" | head -1)
  SENHA=$(echo "$LINHA_ACHADA" | sed -n 's/.*password: *\([^ ]*\).*/\1/p')
  rm -f "$SAIDA_TMP"
  ENCONTRADO_JSON=$(php -r 'echo json_encode(["usuario" => $argv[1], "senha" => $argv[2]]);' -- "$USUARIO" "$SENHA")
  escrever_status "concluido" 100 "Senha fraca encontrada para \"${USUARIO}\"." "$ENCONTRADO_JSON"
else
  rm -f "$SAIDA_TMP"
  escrever_status "concluido" 100 "Nenhuma senha da wordlist funcionou para \"${USUARIO}\"." "null"
fi
