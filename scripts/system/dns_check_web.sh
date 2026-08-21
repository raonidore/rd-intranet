#!/bin/bash
# dns_check_web.sh <dominio>
# Mesma validacao estrita do ping_web.sh/traceroute_web.sh (so hostname,
# nao faz sentido testar resolucao DNS de um IP).

DOMINIO="$1"

RE_HOST='^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$'

if [[ ! "$DOMINIO" =~ $RE_HOST ]]; then
  echo "Destino invalido"
  exit 1
fi

if ! command -v dig >/dev/null 2>&1; then
  echo "dig nao esta instalado. Instale o pacote 'bind9-dnsutils' em Infraestrutura > Dependencias."
  exit 1
fi

echo "RESOLVCONF|$(grep '^nameserver' /etc/resolv.conf 2>/dev/null | awk '{print $2}' | paste -sd, -)"

# nome, servidor ("" = resolver padrao do sistema, via /etc/resolv.conf --
# em Ubuntu 24.04 isso normalmente e o stub local do systemd-resolved
# (127.0.0.53), entao rodar sem "@" reproduz a experiencia real de
# qualquer app na maquina em vez de expor só o IP configurado.
testar() {
  local nome="$1" servidor="$2" alvo=() ini fim resp status ms

  if [ -n "$servidor" ]; then
    alvo=(@"$servidor")
  fi

  ini=$(date +%s%3N)
  resp=$(timeout 5 dig +time=3 +tries=1 "${alvo[@]}" "$DOMINIO" +short 2>&1)
  status=$?
  fim=$(date +%s%3N)
  ms=$((fim - ini))

  if [ $status -ne 0 ] || [ -z "$resp" ]; then
    echo "RESOLVER|$nome|${servidor:--}|falha|$ms"
  else
    echo "RESOLVER|$nome|${servidor:--}|ok|$ms|$(echo "$resp" | head -1)"
  fi
}

testar "Sistema (padrao)" ""
testar "Google" "8.8.8.8"
testar "Cloudflare" "1.1.1.1"
