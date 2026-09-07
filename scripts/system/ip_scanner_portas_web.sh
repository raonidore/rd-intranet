#!/bin/bash
# ip_scanner_portas_web.sh <ip>
#
# Varredura de portas de UM dispositivo ja encontrado pelo scan de faixa
# -- sincrono (alvo unico, limitado as 100 portas mais comuns, mesmo
# espirito de "sincrono com timeout embutido" que mtr_web.sh ja usa).
# De proposito limitado (nao e 65535 portas nem detecta SO) -- fica no
# escopo de inventario do proprio dispositivo, nao vira ferramenta ofensiva.

set -u

IP="$1"

REGEX_IP='^([0-9]{1,3}\.){3}[0-9]{1,3}$'
REGEX_IPV6='^[0-9a-fA-F:]+$'
if [[ ! "$IP" =~ $REGEX_IP ]] && [[ ! "$IP" =~ $REGEX_IPV6 ]]; then
  echo '{"success":false,"message":"IP inválido."}'
  exit 1
fi

command -v nmap >/dev/null 2>&1 || { echo '{"success":false,"message":"nmap não está instalado."}'; exit 1; }

XML_TMP="/tmp/rd_ipscan_portas_$$.xml"

if ! timeout 30 nmap -sV --top-ports 100 -T4 -oX "$XML_TMP" "$IP" >/dev/null 2>&1; then
  rm -f "$XML_TMP"
  echo '{"success":false,"message":"Falha ao escanear portas (tempo esgotado ou host inacessível)."}'
  exit 1
fi

if [ ! -f "$XML_TMP" ]; then
  echo '{"success":false,"message":"Falha ao escanear portas."}'
  exit 1
fi

php -r '
    $xml = @simplexml_load_file($argv[1]);
    if ($xml === false || !isset($xml->host)) {
        echo json_encode(["success" => true, "portas" => []]);
        exit(0);
    }
    $portas = [];
    if (isset($xml->host->ports->port)) {
        foreach ($xml->host->ports->port as $port) {
            $estado = (string)$port->state["state"];
            if ($estado !== "open") { continue; }
            $portas[] = [
                "porta" => (int)$port["portid"],
                "protocolo" => (string)$port["protocol"],
                "servico" => (string)($port->service["name"] ?? ""),
                "versao" => trim(($port->service["product"] ?? "") . " " . ($port->service["version"] ?? "")),
            ];
        }
    }
    echo json_encode(["success" => true, "portas" => $portas]);
' -- "$XML_TMP"

rm -f "$XML_TMP"
