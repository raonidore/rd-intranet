#!/bin/bash
# dhcp_status_web.sh
#
# Status ao vivo do isc-dhcp-server -- servico rodando ou nao, e a lista
# de concessoes (leases) ativas agora, lida direto do arquivo que o
# proprio dhcpd mantem (/var/lib/dhcp/dhcpd.leases). Saida em linhas
# com pipe, mesmo padrao ja usado em vpn_wireguard_status_web.sh (o PHP
# interpreta cada tipo de linha).
#
# O arquivo de leases acumula um bloco novo a cada renovacao -- o MESMO
# IP aparece varias vezes ao longo do tempo (historico), nunca sobrescrito.
# So o ULTIMO bloco de cada IP reflete o estado atual; blocos antigos do
# mesmo IP sao apenas historico e tem que ser ignorados. O awk abaixo
# resolve isso guardando só o bloco mais recente por IP num array,
# processando o arquivo em ordem (blocos mais novos vêm depois no
# arquivo, entao o ultimo que sobrescreve o array e sempre o mais atual).

set -u

if systemctl is-active --quiet isc-dhcp-server; then
  echo "SERVICO|ativo"
else
  echo "SERVICO|parado"
fi

ARQUIVO_LEASES="/var/lib/dhcp/dhcpd.leases"

if [ ! -f "$ARQUIVO_LEASES" ]; then
  exit 0
fi

awk '
  /^lease / {
    ip = $2
    estado[ip] = ""
    mac[ip] = ""
    host[ip] = ""
    termina[ip] = ""
    dentro = 1
    next
  }
  dentro && /^}/ { dentro = 0; next }
  dentro && /^[[:space:]]*binding state/ {
    if (estado[ip] == "") {
      s = $0
      gsub(/^[[:space:]]*binding state[[:space:]]*/, "", s)
      gsub(/;.*/, "", s)
      estado[ip] = s
    }
  }
  dentro && /hardware ethernet/ {
    s = $0
    gsub(/^[[:space:]]*hardware ethernet[[:space:]]*/, "", s)
    gsub(/;.*/, "", s)
    mac[ip] = s
  }
  dentro && /client-hostname/ {
    s = $0
    gsub(/^[[:space:]]*client-hostname[[:space:]]*"/, "", s)
    gsub(/".*/, "", s)
    host[ip] = s
  }
  dentro && /^[[:space:]]*ends/ {
    s = $0
    gsub(/^[[:space:]]*ends[[:space:]]*[0-9][[:space:]]*/, "", s)
    gsub(/;.*/, "", s)
    termina[ip] = s
  }
  END {
    for (ip in estado) {
      if (estado[ip] == "active") {
        printf "LEASE|%s|%s|%s|%s\n", ip, mac[ip], host[ip], termina[ip]
      }
    }
  }
' "$ARQUIVO_LEASES" 2>/dev/null
