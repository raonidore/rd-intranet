#!/bin/bash
# meshcentral_instalar_web.sh
# Instala o MeshCentral (acesso remoto self-hosted, Apache 2.0,
# https://github.com/Ylianst/MeshCentral) como servico systemd proprio,
# rodando como usuario dedicado nao-root. Escuta em porta propria
# (nao atras do Apache -- evita depender de proxy WebSocket, que o
# MeshCentral usa pesado tanto pro agente quanto pra tela remota).
# A exposicao real (rede interna vs. internet) e controlada pelo
# firewall, mesmo padrao ja usado pelas VPNs -- nunca liberado sozinho
# na instalacao.
#
# Roda em SEGUNDO PLANO (LinuxService::executarScriptEmSegundoPlano(),
# nao bloqueia a requisicao HTTP) -- o npm install do MeshCentral sozinho
# ja passa de 1 minuto, e o apt-get do Node.js antes dele pode levar mais.
# Como ninguem fica esperando uma resposta HTTP, o progresso e reportado
# via ARQUIVO DE STATUS (ver escrever_status()), que a tela consulta por
# polling -- e tambem consulta assim que a pagina carrega/e atualizada,
# entao um F5 no meio da instalacao so mostra o progresso de novo, nunca
# some ela nem deixa a pessoa clicar "Instalar" uma segunda vez por achar
# que travou.

set -u

PASTA_INSTALACAO="/opt/meshcentral"
PASTA_DADOS="/opt/meshcentral/meshcentral-data"
USUARIO="meshcentral"
PORTA=4430
STATUS_ARQUIVO="/var/www/rd.intranet/storage/cache/meshcentral_instalacao.json"
INICIADO_EM="$(date +%s)"

export DEBIAN_FRONTEND=noninteractive

escrever_status() {
  # $1=etapa (texto curto pra mostrar na tela), $2=percentual, $3=status (rodando|concluido|erro), $4=mensagem opcional
  local etapa="$1" pct="$2" status="$3" msg="${4:-}"
  msg="${msg//\\/\\\\}"
  msg="${msg//\"/\\\"}"
  etapa="${etapa//\"/\\\"}"
  printf '{"etapa":"%s","percentual":%s,"status":"%s","mensagem":"%s","iniciado_em":%s,"atualizado_em":%s}\n' \
    "$etapa" "$pct" "$status" "$msg" "$INICIADO_EM" "$(date +%s)" > "$STATUS_ARQUIVO"
}

escrever_status "Iniciando" 5 "rodando"

if ! command -v node >/dev/null 2>&1; then
  escrever_status "Instalando Node.js/npm (apt-get)" 15 "rodando"
  if ! apt-get install -y -qq nodejs npm >/tmp/rd_mesh_out_$$ 2>/tmp/rd_mesh_err_$$; then
    ERRO="$(tail -20 /tmp/rd_mesh_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_mesh_out_$$ /tmp/rd_mesh_err_$$
    escrever_status "Erro ao instalar Node.js" 15 "erro" "Erro ao instalar Node.js: ${ERRO}"
    echo "{\"success\":false,\"message\":\"Erro ao instalar Node.js: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_mesh_out_$$ /tmp/rd_mesh_err_$$
fi

escrever_status "Node.js pronto, preparando usuário e pastas" 30 "rodando"

if ! id "$USUARIO" >/dev/null 2>&1; then
  useradd --system --home-dir "$PASTA_INSTALACAO" --shell /usr/sbin/nologin "$USUARIO"
fi

mkdir -p "$PASTA_INSTALACAO" "$PASTA_DADOS"

if [ ! -f "$PASTA_INSTALACAO/package.json" ]; then
  escrever_status "Baixando e instalando o MeshCentral (npm) -- costuma ser a etapa mais demorada" 40 "rodando"
  cd "$PASTA_INSTALACAO" || exit 1
  if ! npm install meshcentral --omit=dev >/tmp/rd_mesh_out_$$ 2>/tmp/rd_mesh_err_$$; then
    ERRO="$(tail -20 /tmp/rd_mesh_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_mesh_out_$$ /tmp/rd_mesh_err_$$
    escrever_status "Erro ao instalar o MeshCentral" 40 "erro" "Erro ao instalar MeshCentral via npm: ${ERRO}"
    echo "{\"success\":false,\"message\":\"Erro ao instalar MeshCentral via npm: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_mesh_out_$$ /tmp/rd_mesh_err_$$
fi

escrever_status "MeshCentral instalado, gerando configuração" 85 "rodando"

# Config minima -- escuta na porta propria em todas as interfaces
# (o firewall decide quem alcanca), sem redirect de porta 80->443
# (redirPort 0, ja temos o Apache cuidando disso pro dominio principal).
# allowFraming permite embutir a tela remota num iframe do RD Intranet.
# allowedorigin:true desliga a checagem de Origin do navegador contra um
# hostname fixo -- sem isso, o MeshCentral so aceita conexao vinda do
# mesmo hostname configurado em "cert", e rejeita com "Invalid origin"
# quem acessa por IP ou por outro nome (nosso caso: acesso tanto por
# hostname quanto por IP na rede interna).
if [ ! -f "$PASTA_DADOS/config.json" ]; then
  cat > "$PASTA_DADOS/config.json" <<EOF
{
  "settings": {
    "cert": "meshcentral",
    "port": ${PORTA},
    "redirPort": 0,
    "allowFraming": true
  },
  "domains": {
    "": {
      "title": "RD Intranet - Acesso Remoto",
      "title2": "",
      "allowedorigin": true
    }
  }
}
EOF
fi

# Idempotente: se o config.json ja existia (reinstalacao/reparo), garante
# que allowedorigin esteja ligado mesmo assim, sem mexer no resto do
# arquivo (ex: senha/2FA/contas ja configuradas continuam intactas).
node -e "
const fs = require('fs');
const caminho = '$PASTA_DADOS/config.json';
const config = JSON.parse(fs.readFileSync(caminho, 'utf8'));
if (!config.domains) config.domains = {};
if (!config.domains['']) config.domains[''] = {};
config.domains[''].allowedorigin = true;
if (!config.settings) config.settings = {};
config.settings.allowFraming = true;
fs.writeFileSync(caminho, JSON.stringify(config, null, 2));
"

chown -R "$USUARIO":"$USUARIO" "$PASTA_INSTALACAO"

cat > /etc/systemd/system/meshcentral.service <<EOF
[Unit]
Description=MeshCentral - Acesso Remoto (RD Intranet)
After=network.target

[Service]
Type=simple
User=${USUARIO}
Group=${USUARIO}
WorkingDirectory=${PASTA_INSTALACAO}
ExecStart=/usr/bin/node node_modules/meshcentral
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

escrever_status "Iniciando o serviço" 95 "rodando"

systemctl daemon-reload
systemctl enable meshcentral >/dev/null 2>&1
systemctl restart meshcentral

sleep 3

if systemctl is-active --quiet meshcentral; then
  MSG="MeshCentral instalado e rodando na porta ${PORTA} (127.0.0.1). Libere a porta no Firewall pra acessar, e crie a primeira conta em https://SEU_SERVIDOR:${PORTA}/"
  escrever_status "Concluído" 100 "concluido" "$MSG"
  echo "{\"success\":true,\"message\":\"${MSG}\"}"
else
  ULTIMO_LOG="$(journalctl -u meshcentral -n 20 --no-pager | tr '\n' ' ' | sed 's/"/\\"/g')"
  MSG="MeshCentral instalado mas o serviço não subiu. Log: ${ULTIMO_LOG}"
  escrever_status "Erro ao iniciar o serviço" 95 "erro" "$MSG"
  echo "{\"success\":false,\"message\":\"${MSG}\"}"
fi
