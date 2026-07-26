#!/bin/bash
# guacamole_bridge_instalar_web.sh
# Instala a ponte WebSocket<->guacd (pacote npm "guacamole-lite") como
# serviço systemd próprio, rodando como usuário dedicado não-root -- mesmo
# molde de meshcentral_instalar_web.sh. Escuta SÓ em 127.0.0.1 (nunca em
# interface pública) -- quem fala com o navegador é o Apache, via proxy
# reverso (ver rdp_proxy_ativar_web.sh), não a ponte diretamente.
#
# Decisão revertida em relação à primeira versão desta feature (porta
# própria + TLS terminado aqui): confirmado ao vivo que isso exige o
# admin liberar mais uma porta no roteador/NAT em CADA servidor pra
# acesso remoto funcionar -- inviável pra quem administra vários
# servidores atrás de NAT com mapeamento mínimo de portas (só a do site).
# Proxy pela mesma porta do site elimina essa exigência, e como bônus a
# ponte nem precisa mais ler certificado nenhum (quem termina TLS agora é
# o Apache) -- fim do problema de permissão do certificado que já foi
# corrigido duas vezes nesse desenho antigo.

set -u

PASTA_INSTALACAO="/opt/rd-guac-bridge"
USUARIO="guacbridge"
PORTA=8092
CHAVE_SEGREDO="/etc/rd-intranet/db_secret.key"

if [ ! -f "$CHAVE_SEGREDO" ]; then
  echo '{"success":false,"message":"Chave de criptografia da aplicação não encontrada em /etc/rd-intranet/db_secret.key."}'
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

if ! command -v node >/dev/null 2>&1; then
  if ! apt-get install -y -qq nodejs npm >/tmp/rd_guacbr_out_$$ 2>/tmp/rd_guacbr_err_$$; then
    ERRO="$(tail -20 /tmp/rd_guacbr_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_guacbr_out_$$ /tmp/rd_guacbr_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar Node.js: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_guacbr_out_$$ /tmp/rd_guacbr_err_$$
fi

if ! id "$USUARIO" >/dev/null 2>&1; then
  useradd --system --home-dir "$PASTA_INSTALACAO" --shell /usr/sbin/nologin "$USUARIO"
fi

mkdir -p "$PASTA_INSTALACAO"

if [ ! -f "$PASTA_INSTALACAO/package.json" ]; then
  cd "$PASTA_INSTALACAO" || exit 1
  if ! npm init -y >/tmp/rd_guacbr_out_$$ 2>/tmp/rd_guacbr_err_$$ || \
     ! npm install guacamole-lite --omit=dev >>/tmp/rd_guacbr_out_$$ 2>>/tmp/rd_guacbr_err_$$; then
    ERRO="$(tail -20 /tmp/rd_guacbr_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_guacbr_out_$$ /tmp/rd_guacbr_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar guacamole-lite via npm: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_guacbr_out_$$ /tmp/rd_guacbr_err_$$
fi

# O usuario dedicado do bridge nao consegue ler /etc/rd-intranet/db_secret.key
# direto (0640 root:www-data -- so root e o grupo www-data, confirmado ao
# vivo: EACCES). Em vez de colocar o bridge no grupo www-data (mais
# acesso do que precisa -- esse grupo tambem cobre storage/uploads etc),
# copia o CONTEUDO da chave pra um arquivo proprio do bridge, dono
# guacbridge, 0600. Refeito a cada instalacao/reparo -- se a chave da
# aplicacao mudar algum dia, um novo "Preparar suporte a RDP" resincroniza.
CHAVE_BRIDGE="${PASTA_INSTALACAO}/shared.key"
cp "$CHAVE_SEGREDO" "$CHAVE_BRIDGE"
chmod 600 "$CHAVE_BRIDGE"

cat > "$PASTA_INSTALACAO/server.js" <<EOF
// Gerado por guacamole_bridge_instalar_web.sh -- não editar na mão, esse
// arquivo é sobrescrito a cada reinstalação/reparo.
const fs = require('fs');
const http = require('http');
const GuacamoleLite = require('guacamole-lite');

const PORTA = ${PORTA};
const chaveCompartilhada = Buffer.from(fs.readFileSync('${PASTA_INSTALACAO}/shared.key', 'utf8').trim(), 'base64');

// TLS quem termina agora e o Apache (proxy reverso) -- a ponte so fala
// com o Apache via loopback, texto puro mesmo, sem risco real.
const servidorHttp = http.createServer();

new GuacamoleLite(
    { server: servidorHttp },
    { host: '127.0.0.1', port: 4822 },
    { crypt: { cypher: 'AES-256-CBC', key: chaveCompartilhada } }
);

servidorHttp.listen(PORTA, '127.0.0.1', () => {
    console.log('Ponte RDP (guacamole-lite) ouvindo em 127.0.0.1:' + PORTA);
});
EOF

chown -R "$USUARIO":"$USUARIO" "$PASTA_INSTALACAO"

cat > /etc/systemd/system/rd-guac-bridge.service <<EOF
[Unit]
Description=RD Intranet - Ponte RDP pelo navegador (guacamole-lite)
After=network.target guacd.service

[Service]
Type=simple
User=${USUARIO}
Group=${USUARIO}
WorkingDirectory=${PASTA_INSTALACAO}
ExecStart=/usr/bin/node server.js
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable rd-guac-bridge >/dev/null 2>&1
systemctl restart rd-guac-bridge

sleep 2

if systemctl is-active --quiet rd-guac-bridge; then
  echo "{\"success\":true,\"message\":\"Ponte RDP instalada e rodando (127.0.0.1:${PORTA}).\"}"
else
  ULTIMO_LOG="$(journalctl -u rd-guac-bridge -n 20 --no-pager 2>/dev/null | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"Ponte RDP instalada mas o serviço não subiu. Log: ${ULTIMO_LOG}\"}"
fi
