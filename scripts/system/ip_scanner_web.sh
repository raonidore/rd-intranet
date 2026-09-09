#!/bin/bash
# ip_scanner_web.sh <execucao_id> <cidr1> [cidr2] [cidr3] ...
#
# Varre uma ou mais faixas de IP privada em busca de dispositivos ativos
# (IP, hostname, MAC, fabricante) via "nmap -sn -R". Roda em segundo plano
# (LinuxService::executarScriptEmSegundoPlano) porque pode levar dezenas
# de segundos a poucos minutos numa faixa grande -- escreve o proprio
# progresso em storage/ip_scanner_status/<execucao_id>.json, mesmo
# contrato (status/percentual/mensagem) ja usado em
# lote_arquivos_samba_web.sh e samba_dc_provisionar_web.sh.
#
# Varias faixas viram uma unica chamada do nmap (nao um loop de chamadas
# separadas) -- o nmap ja aceita multiplos alvos na mesma linha de comando
# e o resultado (e o percentual de progresso) sai naturalmente combinado,
# em vez de precisar somar/mesclar XML de execucoes distintas depois.
#
# O percentual reportado enquanto roda e REAL (nao estimado): vem de
# "nmap --stats-every 2s", que imprime periodicamente no stderr algo como
# "About 45.00% done" -- o nmap roda em segundo plano dentro deste
# script (com "&") enquanto um loop le esse arquivo e atualiza o status,
# depois um "wait" garante que so seguimos pro parse do XML quando o nmap
# realmente terminou.

set -u

STATUS_DIR="/var/www/rd.intranet/storage/ip_scanner_status"
mkdir -p "$STATUS_DIR"

EXECUCAO_ID="$1"
shift
CIDRS=("$@")

STATUS_FILE="$STATUS_DIR/${EXECUCAO_ID}.json"

escrever_status() {
  local status="$1" percentual="$2" mensagem="$3" resultados_json="${4:-[]}"
  php -r '
    $status = $argv[1];
    $percentual = (int)$argv[2];
    $mensagem = $argv[3];
    $resultados = json_decode($argv[4], true);
    echo json_encode([
        "status" => $status,
        "percentual" => min(100, max(0, $percentual)),
        "mensagem" => $mensagem,
        "resultados" => is_array($resultados) ? $resultados : [],
        "atualizado_em" => time(),
    ]);
  ' -- "$status" "$percentual" "$mensagem" "$resultados_json" > "$STATUS_FILE"
  chmod 644 "$STATUS_FILE"
}

if [[ ! "$EXECUCAO_ID" =~ ^[a-f0-9]+$ ]]; then
  echo "ID de execucao invalido" >&2
  exit 1
fi

if [ "${#CIDRS[@]}" -eq 0 ]; then
  escrever_status "erro" 0 "Informe pelo menos uma faixa de IP."
  exit 1
fi

if [ "${#CIDRS[@]}" -gt 8 ]; then
  escrever_status "erro" 0 "Máximo de 8 faixas por varredura."
  exit 1
fi

# ── Validacao de cada CIDR (redundante a validacao do PHP -- nunca confiar
# so nela): formato basico + rede privada (RFC1918) ou link-local +
# tamanho maximo /22 (1024 enderecos) ────────────────────────────────
for CIDR in "${CIDRS[@]}"; do
  if [[ ! "$CIDR" =~ ^([0-9]{1,3})\.([0-9]{1,3})\.[0-9]{1,3}\.[0-9]{1,3}/([0-9]{1,2})$ ]]; then
    escrever_status "erro" 0 "Faixa de IP inválida: ${CIDR}."
    exit 1
  fi

  OCTETO1="${BASH_REMATCH[1]}"
  OCTETO2="${BASH_REMATCH[2]}"
  PREFIXO="${BASH_REMATCH[3]}"

  PRIVADA=0
  if [ "$OCTETO1" -eq 10 ]; then
    PRIVADA=1
  elif [ "$OCTETO1" -eq 172 ] && [ "$OCTETO2" -ge 16 ] && [ "$OCTETO2" -le 31 ]; then
    PRIVADA=1
  elif [ "$OCTETO1" -eq 192 ] && [ "$OCTETO2" -eq 168 ]; then
    PRIVADA=1
  elif [ "$OCTETO1" -eq 169 ] && [ "$OCTETO2" -eq 254 ]; then
    PRIVADA=1
  fi

  if [ "$PRIVADA" -eq 0 ]; then
    escrever_status "erro" 0 "Só é permitido varrer faixas de rede privada (RFC1918) ou link-local: ${CIDR}."
    exit 1
  fi

  if [ "$PREFIXO" -lt 22 ]; then
    escrever_status "erro" 0 "Faixa grande demais (máx. /22): ${CIDR}."
    exit 1
  fi
done

command -v nmap >/dev/null 2>&1 || { escrever_status "erro" 0 "nmap não está instalado. Instale em Infraestrutura > Dependências."; exit 1; }

CIDRS_LABEL=$(IFS=', '; echo "${CIDRS[*]}")
escrever_status "rodando" 0 "Iniciando varredura de ${CIDRS_LABEL}..."

XML_TMP="/tmp/rd_ipscan_${EXECUCAO_ID}.xml"
STATS_TMP="/tmp/rd_ipscan_stats_${EXECUCAO_ID}.log"

nmap -sn -R --stats-every 2s -oX "$XML_TMP" "${CIDRS[@]}" >"$STATS_TMP" 2>&1 &
NMAP_PID=$!

while kill -0 "$NMAP_PID" 2>/dev/null; do
  PCT=$(grep -oE 'About [0-9]+\.[0-9]+% done' "$STATS_TMP" 2>/dev/null | tail -1 | grep -oE '[0-9]+\.[0-9]+' | cut -d. -f1)
  if [ -n "${PCT:-}" ]; then
    escrever_status "rodando" "$PCT" "Varrendo ${CIDRS_LABEL}... (${PCT}%)"
  fi
  sleep 1
done

wait "$NMAP_PID"
CODIGO=$?
rm -f "$STATS_TMP"

if [ "$CODIGO" -ne 0 ] || [ ! -f "$XML_TMP" ]; then
  escrever_status "erro" 0 "Falha ao executar o nmap (código ${CODIGO})."
  rm -f "$XML_TMP"
  exit 1
fi

escrever_status "rodando" 95 "Resolvendo nomes NetBIOS pendentes..."

# ── Parseia o XML do nmap e devolve uma linha "ip|hostname|mac|vendor"
# por host encontrado (campos ausentes viram string vazia) ───────────
LINHAS=()
while IFS= read -r LINHA; do
  [ -z "$LINHA" ] && continue
  LINHAS+=("$LINHA")
done < <(php -r '
    $xml = @simplexml_load_file($argv[1]);
    if ($xml === false) { exit(0); }
    foreach ($xml->host as $host) {
        $ip = ""; $mac = ""; $vendor = "";
        foreach ($host->address as $addr) {
            $tipo = (string)$addr["addrtype"];
            if ($tipo === "ipv4" || $tipo === "ipv6") { $ip = (string)$addr["addr"]; }
            if ($tipo === "mac") { $mac = (string)$addr["addr"]; $vendor = (string)$addr["vendor"]; }
        }
        if ($ip === "") { continue; }
        $hostname = "";
        if (isset($host->hostnames->hostname)) {
            $hostname = (string)$host->hostnames->hostname["name"];
        }
        $linha = str_replace("|", "", $ip) . "|" . str_replace("|", "", $hostname) . "|" . str_replace("|", "", $mac) . "|" . str_replace("|", "", $vendor);
        echo $linha . "\n";
    }
' -- "$XML_TMP")
rm -f "$XML_TMP"

# ── NetBIOS de reforco pra quem nao tem hostname via DNS ─────────────
LINHAS_FINAIS=()
for LINHA in "${LINHAS[@]}"; do
  IFS='|' read -r IP HOSTNAME MAC VENDOR <<< "$LINHA"
  if [ -z "$HOSTNAME" ] && command -v nmblookup >/dev/null 2>&1; then
    NB=$(timeout 2 nmblookup -A "$IP" 2>/dev/null | grep -oE '^\s*\S+\s+<20>' | awk '{print $1}' | head -1)
    [ -n "${NB:-}" ] && HOSTNAME="$NB"
  fi
  LINHAS_FINAIS+=("${IP}|${HOSTNAME}|${MAC}|${VENDOR}")
done

if [ "${#LINHAS_FINAIS[@]}" -eq 0 ]; then
  RESULTADOS_JSON="[]"
else
  RESULTADOS_JSON=$(php -r '
      $itens = [];
      for ($i = 1; $i < count($argv); $i++) {
          [$ip, $hostname, $mac, $vendor] = array_pad(explode("|", $argv[$i], 4), 4, "");
          $itens[] = ["ip" => $ip, "hostname" => $hostname, "mac" => $mac, "vendor" => $vendor];
      }
      echo json_encode($itens);
  ' -- "${LINHAS_FINAIS[@]}")
fi

TOTAL="${#LINHAS_FINAIS[@]}"
escrever_status "concluido" 100 "Varredura concluída: ${TOTAL} dispositivo(s) encontrado(s)." "$RESULTADOS_JSON"
