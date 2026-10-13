#!/bin/bash
# wifi_filtro_instalar_web.sh <ip_lan> <usuario>   (senha da API pelo stdin)
#
# Instala (ou reconfigura) o AdGuard Home usado como filtro de sites do
# Wi-Fi do portal: DNS em <ip_lan>:53 (os aparelhos do Wi-Fi recebem esse
# DNS pelo DHCP da rede de visitantes) e painel/API somente em
# 127.0.0.1:3010 -- quem conversa com ele é o RD.Intranet (WifiFiltroService).
# Versão fixada de propósito: atualizar aqui depois de testar.

set -u

VERSAO="v0.107.52"
DIR="/opt/AdGuardHome"
IP="${1:-}"
USUARIO="${2:-rdintranet}"
IFS= read -r SENHA || true

json() { python3 -c 'import json,sys; print(json.dumps({"success": sys.argv[1] == "1", "message": sys.argv[2]}))' "$1" "$2"; }

if ! [[ "$IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then json 0 "IP inválido."; exit 1; fi
if ! [[ "$USUARIO" =~ ^[a-z0-9_]+$ ]]; then json 0 "Usuário inválido."; exit 1; fi
if [ ${#SENHA} -lt 12 ]; then json 0 "Senha da API ausente."; exit 1; fi
if ! ip -4 addr | grep -q "inet ${IP}/"; then json 0 "O IP ${IP} não é deste servidor."; exit 1; fi

# Porta 53 ocupada por outro programa nesse IP (o stub do systemd-resolved fica em 127.0.0.53 e não atrapalha).
OCUPA="$(ss -lunpH "( sport = :53 )" 2>/dev/null | grep -E "(${IP//./\\.}|0\.0\.0\.0|\*):53 " | grep -v AdGuardHome)"
if [ -n "$OCUPA" ]; then
  json 0 "A porta 53 (DNS) deste servidor já está em uso por outro programa: $(echo "$OCUPA" | grep -o 'users:(("[^"]*' | head -1 | cut -d'"' -f2). Desative-o antes."
  exit 1
fi

case "$(uname -m)" in
  x86_64) ARQ="amd64" ;;
  aarch64) ARQ="arm64" ;;
  armv7l) ARQ="armv7" ;;
  *) json 0 "Arquitetura $(uname -m) não suportada."; exit 1 ;;
esac

if [ ! -x "$DIR/AdGuardHome" ]; then
  TMP="$(mktemp -d)"
  if ! curl -fsSL --max-time 180 -o "$TMP/agh.tar.gz" "https://github.com/AdguardTeam/AdGuardHome/releases/download/${VERSAO}/AdGuardHome_linux_${ARQ}.tar.gz"; then
    rm -rf "$TMP"; json 0 "Falha ao baixar o AdGuard Home ${VERSAO}."; exit 1
  fi
  tar -xzf "$TMP/agh.tar.gz" -C /opt || { rm -rf "$TMP"; json 0 "Falha ao extrair o pacote."; exit 1; }
  rm -rf "$TMP"
fi

if [ ! -f "$DIR/AdGuardHome.yaml" ]; then
  # Primeira vez: o assistente sobe em :3000 e é configurado pela API dele.
  "$DIR/AdGuardHome" -s install >/dev/null 2>&1 || true
  systemctl start AdGuardHome 2>/dev/null
  for _ in $(seq 1 30); do
    curl -fs -o /dev/null http://127.0.0.1:3000/ && break
    sleep 1
  done
  CORPO="$(python3 -c 'import json,sys; print(json.dumps({"web":{"ip":"127.0.0.1","port":3010},"dns":{"ip":sys.argv[1],"port":53},"username":sys.argv[2],"password":sys.argv[3]}))' "$IP" "$USUARIO" "$SENHA")"
  RESP="$(curl -s -w ' HTTP%{http_code}' -X POST -H 'Content-Type: application/json' --data "$CORPO" http://127.0.0.1:3000/control/install/configure)"
  if ! echo "$RESP" | grep -q 'HTTP200'; then
    json 0 "O assistente do AdGuard recusou a configuração: ${RESP}"; exit 1
  fi
else
  # Já instalado: garante usuário/senha, endereços e reinicia.
  HASH="$(htpasswd -bnBC 10 "" "$SENHA" 2>/dev/null | tr -d ':\n' | sed 's/^\$2y/$2a/')"
  if [ -z "$HASH" ]; then json 0 "htpasswd (apache2-utils) não encontrado."; exit 1; fi
  python3 - "$DIR/AdGuardHome.yaml" "$IP" "$USUARIO" "$HASH" <<'PY'
import re, sys
caminho, ip, usuario, hash_ = sys.argv[1:5]
t = open(caminho).read()
# Sem a flag "s": ".*" não pode atravessar linhas, senão engole o resto do arquivo.
t = re.sub(r'(?m)^users:\n(?:[ -].*\n)*', 'users:\n  - name: %s\n    password: %s\n' % (usuario, hash_), t, count=1)
t = re.sub(r'(?m)^(http:\n(?:  .*\n)*?  address: ).*$', r'\g<1>127.0.0.1:3010', t, count=1)
t = re.sub(r'(?m)^(dns:\n  bind_hosts:\n)(?:    - .*\n)+', r'\g<1>    - %s\n' % ip, t, count=1)
open(caminho, 'w').write(t)
PY
  systemctl restart AdGuardHome
fi

systemctl enable AdGuardHome >/dev/null 2>&1
for _ in $(seq 1 20); do
  if curl -fs -o /dev/null -u "${USUARIO}:${SENHA}" http://127.0.0.1:3010/control/status; then
    json 1 "AdGuard Home ${VERSAO} ativo: DNS em ${IP}:53, API em 127.0.0.1:3010."
    exit 0
  fi
  sleep 1
done
json 0 "AdGuard Home instalado mas a API não respondeu em 127.0.0.1:3010 (journalctl -u AdGuardHome)."
exit 1
