#!/bin/bash
# whatsapp_bridge_instalar_web.sh <repo_dir> <porta> <api_key> <webhook_url>
#
# Instala/reinstala o bridge WhatsApp (whatsapp-bridge/, Baileys) como
# serviço systemd próprio, seguindo o mesmo padrão já usado pro
# MeshCentral neste projeto (meshcentral_instalar_web.sh): Node.js fora
# do ciclo de vida do PHP, rodando como usuário de sistema dedicado,
# falando com o PHP só por HTTP local (127.0.0.1).
#
# Reinstalação (rodar de novo com o bridge já existente) é segura:
# preserva a sessão do WhatsApp já pareada (não mexe em sessao/), só
# atualiza o código/dependências e reescreve config.json.

set -u

REPO_DIR="${1:?informe o diretorio do checkout}"
PORTA="${2:?informe a porta}"
API_KEY="${3:?informe a api key}"
WEBHOOK_URL="${4:?informe a url do webhook}"

PASTA_INSTALACAO="/opt/rdtecnologia/whatsapp-bridge"
USUARIO="whatsapp-bridge"

export DEBIAN_FRONTEND=noninteractive

if ! command -v node >/dev/null 2>&1; then
  if ! apt-get install -y -qq nodejs npm >/tmp/rd_wpp_out_$$ 2>/tmp/rd_wpp_err_$$; then
    ERRO="$(tail -20 /tmp/rd_wpp_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_wpp_out_$$ /tmp/rd_wpp_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar Node.js: ${ERRO}\"}"
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

cat > /etc/systemd/system/whatsapp-bridge.service <<EOF
[Unit]
Description=RD Intranet - Bridge WhatsApp (Baileys)
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
systemctl enable whatsapp-bridge >/dev/null 2>&1
systemctl restart whatsapp-bridge

sleep 3

if systemctl is-active --quiet whatsapp-bridge; then
  echo "{\"success\":true,\"message\":\"Bridge instalado e rodando na porta ${PORTA} (127.0.0.1). Volte pra tela de integração pra escanear o QR Code.\"}"
else
  ULTIMO_LOG="$(journalctl -u whatsapp-bridge -n 20 --no-pager | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"Bridge instalado mas o serviço não subiu. Log: ${ULTIMO_LOG}\"}"
fi
