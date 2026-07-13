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

set -u

PASTA_INSTALACAO="/opt/meshcentral"
PASTA_DADOS="/opt/meshcentral/meshcentral-data"
USUARIO="meshcentral"
PORTA=4430

export DEBIAN_FRONTEND=noninteractive

if ! command -v node >/dev/null 2>&1; then
  if ! apt-get install -y -qq nodejs npm >/tmp/rd_mesh_out_$$ 2>/tmp/rd_mesh_err_$$; then
    ERRO="$(tail -20 /tmp/rd_mesh_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_mesh_out_$$ /tmp/rd_mesh_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar Node.js: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_mesh_out_$$ /tmp/rd_mesh_err_$$
fi

if ! id "$USUARIO" >/dev/null 2>&1; then
  useradd --system --home-dir "$PASTA_INSTALACAO" --shell /usr/sbin/nologin "$USUARIO"
fi

mkdir -p "$PASTA_INSTALACAO" "$PASTA_DADOS"

if [ ! -f "$PASTA_INSTALACAO/package.json" ]; then
  cd "$PASTA_INSTALACAO" || exit 1
  if ! npm install meshcentral --omit=dev >/tmp/rd_mesh_out_$$ 2>/tmp/rd_mesh_err_$$; then
    ERRO="$(tail -20 /tmp/rd_mesh_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_mesh_out_$$ /tmp/rd_mesh_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar MeshCentral via npm: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_mesh_out_$$ /tmp/rd_mesh_err_$$
fi

# Config minima -- escuta na porta propria em todas as interfaces
# (o firewall decide quem alcanca), sem redirect de porta 80->443
# (redirPort 0, ja temos o Apache cuidando disso pro dominio principal).
# allowFraming permite embutir a tela remota num iframe do RD Intranet.
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
      "title2": ""
    }
  }
}
EOF
fi

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

systemctl daemon-reload
systemctl enable meshcentral >/dev/null 2>&1
systemctl restart meshcentral

sleep 3

if systemctl is-active --quiet meshcentral; then
  echo "{\"success\":true,\"message\":\"MeshCentral instalado e rodando na porta ${PORTA} (127.0.0.1). Libere a porta no Firewall pra acessar, e crie a primeira conta em https://SEU_SERVIDOR:${PORTA}/\"}"
else
  ULTIMO_LOG="$(journalctl -u meshcentral -n 20 --no-pager | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"MeshCentral instalado mas o servico nao subiu. Log: ${ULTIMO_LOG}\"}"
fi
