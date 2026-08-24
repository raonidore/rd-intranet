#!/bin/bash
# chat_bridge_instalar_web.sh <repo_dir> <porta> <api_key> <validar_token_url>
#
# Instala/reinstala o chat-bridge (chat-bridge/, WebSocket) como serviço
# systemd próprio -- mesmo padrão já usado pro bridge do WhatsApp
# (whatsapp_bridge_instalar_web.sh) e pro MeshCentral neste projeto:
# Node.js fora do ciclo de vida do PHP, usuário de sistema dedicado,
# falando com o PHP só por HTTP local (127.0.0.1). Diferente do bridge
# do WhatsApp, este processo não guarda nenhum estado em disco (sem
# "sessao/" pra preservar) -- reinstalar é sempre uma substituição
# limpa.
#
# Depois de instalar o serviço, garante o proxy reverso WebSocket no
# Apache: sem isso o navegador não tem como abrir o socket, já que o
# chat-bridge só ouve em 127.0.0.1 (nunca exposto direto na rede,
# nenhuma regra de firewall nova precisa existir).
#
# IMPORTANTE: o proxy vai num conf-available GLOBAL (mesmo mecanismo de
# /etc/apache2/conf-available/rd-intranet-alias.conf, que é como
# "/rd.intranet" funciona pra QUALQUER Host: recebido -- essa alias é
# um Alias solto em conf-enabled/, não dentro de nenhum <VirtualHost>).
# Este servidor tem NameVirtualHost com dois vhosts na porta 80
# (000-default.conf sem ServerName -- é o "last resort" pra Host não
# reconhecido -- e rd-intranet.conf com ServerName "enzilabprime"), e o
# Host: que o navegador de verdade manda (o IP/domínio que ele digitou
# pra acessar o site) não bate com nenhum ServerName configurado --
# cai sempre no vhost "last resort". Um ProxyPass dentro do
# <VirtualHost> de rd-intranet.conf (como rdp_proxy_ativar_web.sh faz
# pro RDP, mas ali dentro do vhost SSL) só seria alcançado se o Host:
# batesse com "enzilabprime", o que não acontece na prática -- por
# isso aqui o proxy entra como conf global, do jeito que já funciona
# comprovadamente pra "/rd.intranet". Idempotente, e só recarrega o
# Apache depois de "apache2ctl configtest" confirmar que a config ficou
# válida -- nunca aplica uma config potencialmente quebrada no Apache
# rodando.

set -u

REPO_DIR="${1:?informe o diretorio do checkout}"
PORTA="${2:?informe a porta}"
API_KEY="${3:?informe a api key}"
VALIDAR_TOKEN_URL="${4:?informe a url de validacao de token}"

PASTA_INSTALACAO="/opt/rdtecnologia/chat-bridge"
USUARIO="chat-bridge"

export DEBIAN_FRONTEND=noninteractive

NODE_MAJOR_MINIMO=20

precisa_instalar_node() {
  if ! command -v node >/dev/null 2>&1; then
    return 0
  fi

  local maior
  maior="$(node -v | sed -E 's/^v([0-9]+)\..*/\1/')"

  [ -z "$maior" ] || [ "$maior" -lt "$NODE_MAJOR_MINIMO" ]
}

if precisa_instalar_node; then
  if ! curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/tmp/rd_chat_out_$$ 2>/tmp/rd_chat_err_$$; then
    ERRO="$(tail -20 /tmp/rd_chat_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_chat_out_$$ /tmp/rd_chat_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao configurar o repositório do Node.js 20+ (NodeSource): ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_chat_out_$$ /tmp/rd_chat_err_$$

  if ! apt-get install -y -qq nodejs >/tmp/rd_chat_out_$$ 2>/tmp/rd_chat_err_$$; then
    ERRO="$(tail -20 /tmp/rd_chat_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_chat_out_$$ /tmp/rd_chat_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar Node.js 20+: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_chat_out_$$ /tmp/rd_chat_err_$$
fi

if ! id "$USUARIO" >/dev/null 2>&1; then
  useradd --system --home-dir "$PASTA_INSTALACAO" --shell /usr/sbin/nologin "$USUARIO"
fi

rm -rf "$PASTA_INSTALACAO"
mkdir -p "$PASTA_INSTALACAO"
cp -r "$REPO_DIR/chat-bridge/." "$PASTA_INSTALACAO/"

cat > "$PASTA_INSTALACAO/config.json" <<EOF
{
  "porta": ${PORTA},
  "apiKey": "${API_KEY}",
  "validarTokenUrl": "${VALIDAR_TOKEN_URL}"
}
EOF

cd "$PASTA_INSTALACAO" || exit 1

if ! npm ci --omit=dev >/tmp/rd_chat_out_$$ 2>/tmp/rd_chat_err_$$; then
  ERRO="$(tail -20 /tmp/rd_chat_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_chat_out_$$ /tmp/rd_chat_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar dependências (npm ci): ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_chat_out_$$ /tmp/rd_chat_err_$$

chown -R "$USUARIO":"$USUARIO" "$PASTA_INSTALACAO"
chmod 600 "$PASTA_INSTALACAO/config.json"

cat > /etc/systemd/system/chat-bridge.service <<EOF
[Unit]
Description=RD Intranet - Chat interno (WebSocket)
After=network.target

[Service]
Type=simple
User=${USUARIO}
Group=${USUARIO}
WorkingDirectory=${PASTA_INSTALACAO}
ExecStart=/usr/bin/node index.js
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable chat-bridge >/dev/null 2>&1
systemctl restart chat-bridge

sleep 2

if ! systemctl is-active --quiet chat-bridge; then
  ULTIMO_LOG="$(journalctl -u chat-bridge -n 20 --no-pager | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"Chat-bridge instalado mas o serviço não subiu. Log: ${ULTIMO_LOG}\"}"
  exit 1
fi

# --- Proxy reverso WebSocket no Apache (conf global, não dentro de
#     nenhum <VirtualHost> -- ver explicação no topo do arquivo) ------
CONF_DISPONIVEL="/etc/apache2/conf-available/rd-chat-ws-proxy.conf"
CONF_NOME="rd-chat-ws-proxy"

a2enmod proxy >/dev/null 2>&1
a2enmod proxy_wstunnel >/dev/null 2>&1

if [ -f "$CONF_DISPONIVEL" ]; then
  cp "$CONF_DISPONIVEL" "${CONF_DISPONIVEL}.bak-chat-bridge"
fi

cat > "$CONF_DISPONIVEL" <<EOF
# RD Intranet - proxy do chat em tempo real (conf global -- alcança
# qualquer Host: recebido, mesmo mecanismo de rd-intranet-alias.conf).
# Gerado por chat_bridge_instalar_web.sh -- reinstalar reescreve este
# arquivo inteiro, não edite manualmente.
ProxyPass "/chat-ws" "ws://127.0.0.1:${PORTA}/"
ProxyPassReverse "/chat-ws" "ws://127.0.0.1:${PORTA}/"
EOF
chmod 644 "$CONF_DISPONIVEL"

a2enconf "$CONF_NOME" >/dev/null 2>&1

if ! apache2ctl configtest >/dev/null 2>&1; then
  ERRO="$(apache2ctl configtest 2>&1 | tr '\n' ' ' | sed 's/"/\\"/g')"
  a2disconf "$CONF_NOME" >/dev/null 2>&1
  if [ -f "${CONF_DISPONIVEL}.bak-chat-bridge" ]; then
    cp "${CONF_DISPONIVEL}.bak-chat-bridge" "$CONF_DISPONIVEL"
  else
    rm -f "$CONF_DISPONIVEL"
  fi
  echo "{\"success\":true,\"message\":\"Chat-bridge instalado e rodando, mas a configuração do Apache ficou inválida depois de adicionar o proxy -- nada foi recarregado, o conf novo foi desfeito. Saída: ${ERRO}\"}"
  exit 0
fi

systemctl reload apache2

echo "{\"success\":true,\"message\":\"Chat-bridge instalado e rodando na porta ${PORTA} (127.0.0.1), com proxy WebSocket ativo em /chat-ws (mesma porta do site, alcançável de qualquer host).\"}"
