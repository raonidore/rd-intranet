#!/bin/bash
# whatsapp_bridge_instalar_web.sh <repo_dir> <porta> <api_key> <webhook_url> <pasta_instalacao> <usuario_sistema> <unit_systemd>
#
# Instala/reinstala UMA conexao do bridge WhatsApp (whatsapp-bridge/,
# Baileys) como servico systemd proprio, seguindo o mesmo padrao ja
# usado pro MeshCentral neste projeto (meshcentral_instalar_web.sh):
# Node.js fora do ciclo de vida do PHP, rodando como usuario de sistema
# dedicado, falando com o PHP so por HTTP local (127.0.0.1).
#
# Um cliente pode ter varios departamentos, cada um com seu proprio
# numero -- cada conexao (linha de `whatsapp_conexoes`) roda seu proprio
# processo, com diretorio/usuario/porta/unit systemd proprios, todos
# passados explicitamente pelo PHP (WhatsAppConexaoService::criar()) --
# nada e derivado aqui dentro, pra so existir uma fonte de verdade (o
# banco). Pra reinstalar a conexao "Principal" (a que ja rodava antes
# de existir esse conceito de multiplas conexoes), o PHP passa
# exatamente os mesmos valores fixos de sempre
# (/opt/rdtecnologia/whatsapp-bridge, usuario whatsapp-bridge, unit
# whatsapp-bridge.service) -- comportamento identico ao de antes.
#
# Reinstalação (rodar de novo com o bridge já existente) é segura:
# preserva a sessão do WhatsApp já pareada (não mexe em sessao/), só
# atualiza o código/dependências e reescreve config.json.

set -u

REPO_DIR="${1:?informe o diretorio do checkout}"
PORTA="${2:?informe a porta}"
API_KEY="${3:?informe a api key}"
WEBHOOK_URL="${4:?informe a url do webhook}"
PASTA_INSTALACAO="${5:?informe o diretorio de instalacao dessa conexao}"
USUARIO="${6:?informe o usuario de sistema dessa conexao}"
UNIT_SYSTEMD="${7:?informe o nome da unit systemd dessa conexao}"

export DEBIAN_FRONTEND=noninteractive

# Baileys exige Node 20+ (checagem própria dele, via preinstall
# script) -- o pacote "nodejs" do apt do Ubuntu 24.04 traz só a 18.x,
# então "node existe" sozinho não basta (visto ao vivo: servidor já
# tinha Node 18 de outra aplicação, "npm ci" falhava sem essa versão
# nova, e o script morria ali sem nunca chegar a criar o serviço
# systemd -- por isso o bridge ficava "instalado" pela metade e o
# painel via só "bridge não respondeu", sem pista nenhuma do motivo).
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
  if ! curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/tmp/rd_wpp_out_$$ 2>/tmp/rd_wpp_err_$$; then
    ERRO="$(tail -20 /tmp/rd_wpp_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_wpp_out_$$ /tmp/rd_wpp_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao configurar o repositório do Node.js 20+ (NodeSource): ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_wpp_out_$$ /tmp/rd_wpp_err_$$

  if ! apt-get install -y -qq nodejs >/tmp/rd_wpp_out_$$ 2>/tmp/rd_wpp_err_$$; then
    ERRO="$(tail -20 /tmp/rd_wpp_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_wpp_out_$$ /tmp/rd_wpp_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar Node.js 20+: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_wpp_out_$$ /tmp/rd_wpp_err_$$
fi

if ! id "$USUARIO" >/dev/null 2>&1; then
  useradd --system --home-dir "$PASTA_INSTALACAO" --shell /usr/sbin/nologin "$USUARIO"
fi

mkdir -p "$PASTA_INSTALACAO"

# Preserva a sessao/ (credenciais ja pareadas) de uma instalacao
# anterior -- so o codigo do bridge em si e substituido pelo do repo
# (nunca node_modules/config.json antigos, que ficam de fora do
# checkout mesmo, nem a sessao).
PASTA_SESSAO_TEMP="/tmp/rd_wpp_sessao_$$"
if [ -d "$PASTA_INSTALACAO/sessao" ]; then
  mv "$PASTA_INSTALACAO/sessao" "$PASTA_SESSAO_TEMP"
fi

rm -rf "$PASTA_INSTALACAO"
mkdir -p "$PASTA_INSTALACAO"
cp -r "$REPO_DIR/whatsapp-bridge/." "$PASTA_INSTALACAO/"

if [ -d "$PASTA_SESSAO_TEMP" ]; then
  mv "$PASTA_SESSAO_TEMP" "$PASTA_INSTALACAO/sessao"
fi

cat > "$PASTA_INSTALACAO/config.json" <<EOF
{
  "porta": ${PORTA},
  "apiKey": "${API_KEY}",
  "webhookUrl": "${WEBHOOK_URL}"
}
EOF

cd "$PASTA_INSTALACAO" || exit 1

# npm ci exige package-lock.json (commitado no repo) e instala exatamente
# as versões travadas -- mais previsível que "npm install" num servidor
# de produção.
if ! npm ci --omit=dev >/tmp/rd_wpp_out_$$ 2>/tmp/rd_wpp_err_$$; then
  ERRO="$(tail -20 /tmp/rd_wpp_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
  rm -f /tmp/rd_wpp_out_$$ /tmp/rd_wpp_err_$$
  echo "{\"success\":false,\"message\":\"Erro ao instalar dependências (npm ci): ${ERRO}\"}"
  exit 1
fi
rm -f /tmp/rd_wpp_out_$$ /tmp/rd_wpp_err_$$

chown -R "$USUARIO":"$USUARIO" "$PASTA_INSTALACAO"
chmod 600 "$PASTA_INSTALACAO/config.json"

# Nome do servico pro systemctl -- aceita com ou sem ".service", mas tira
# o sufixo pra evitar "whatsapp-bridge-2.service.service" se alguem
# informar o parametro ja com ele.
UNIT_NOME="${UNIT_SYSTEMD%.service}"

cat > "/etc/systemd/system/${UNIT_NOME}.service" <<EOF
[Unit]
Description=RD Intranet - Bridge WhatsApp (Baileys) - ${UNIT_NOME}
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
systemctl enable "$UNIT_NOME" >/dev/null 2>&1
systemctl restart "$UNIT_NOME"

sleep 3

if systemctl is-active --quiet "$UNIT_NOME"; then
  echo "{\"success\":true,\"message\":\"Bridge instalado e rodando na porta ${PORTA} (127.0.0.1). Volte pra tela de integração pra escanear o QR Code.\"}"
else
  ULTIMO_LOG="$(journalctl -u "$UNIT_NOME" -n 20 --no-pager | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"Bridge instalado mas o serviço não subiu. Log: ${ULTIMO_LOG}\"}"
fi
