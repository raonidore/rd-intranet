#!/bin/bash
# guacamole_bridge_instalar_web.sh
# Instala a ponte WebSocket<->guacd (pacote npm "guacamole-lite") como
# serviço systemd próprio, rodando como usuário dedicado não-root -- mesmo
# molde de meshcentral_instalar_web.sh. Escuta na própria porta (não atrás
# do Apache -- mesma decisão já documentada em meshcentral_instalar_web.sh
# sobre WebSocket atrás de proxy reverso ser frágil), com TLS/WSS
# terminado pelo próprio Node reaproveitando o MESMO certificado que o
# Apache já usa (/etc/ssl/rd-intranet/atual.{crt,key}) -- assim o
# navegador não recebe aviso de certificado não confiável.

set -u

PASTA_INSTALACAO="/opt/rd-guac-bridge"
USUARIO="guacbridge"
PORTA=8092
CERT="/etc/ssl/rd-intranet/atual.crt"
CHAVE_TLS="/etc/ssl/rd-intranet/atual.key"
CHAVE_SEGREDO="/etc/rd-intranet/db_secret.key"

if [ ! -f "$CERT" ] || [ ! -f "$CHAVE_TLS" ]; then
  echo '{"success":false,"message":"Nenhum certificado HTTPS ativo em /etc/ssl/rd-intranet/ -- configure em Infraestrutura > Certificado Digital antes de ativar RDP pelo navegador."}'
  exit 1
fi

# Garante leitura via grupo "ssl-cert" na chave privada -- em servidores
# onde o HTTPS foi ativado ANTES desta feature existir, o arquivo ainda
# esta com a permissao antiga (600 root:root) e o bridge (usuario
# dedicado nao-root) nao consegue ler, mesmo depois do proprio
# certificado_ativar_web.sh ja ter sido corrigido no codigo -- aquela
# correcao so e reaplicada quando o certificado e reativado/trocado, nao
# retroativamente. Reaplicado aqui tambem (idempotente, nunca muda o
# dono real nem afrouxa "outros") pra o instalador da ponte nao depender
# de ninguem ter reativado o HTTPS antes de rodar isso.
chgrp ssl-cert /etc/ssl/rd-intranet 2>/dev/null || true
chmod 750 /etc/ssl/rd-intranet
chgrp ssl-cert "$CHAVE_TLS" 2>/dev/null || true
chmod 640 "$CHAVE_TLS"

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

# Grupo "ssl-cert" e a convencao padrao Debian/Ubuntu pra servicos nao-root
# lerem chave privada TLS sem precisar rodar como root -- so adiciona
# leitura de grupo, nunca afrouxa "outros" nem muda o dono real (continua
# root). certificado_ativar_web.sh preserva esse grupo em toda troca de
# certificado (ver comentario la).
usermod -aG ssl-cert "$USUARIO"

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
const https = require('https');
const GuacamoleLite = require('guacamole-lite');

const PORTA = ${PORTA};
const chaveCompartilhada = Buffer.from(fs.readFileSync('${PASTA_INSTALACAO}/shared.key', 'utf8').trim(), 'base64');

const servidorHttps = https.createServer({
    cert: fs.readFileSync('${CERT}'),
    key: fs.readFileSync('${CHAVE_TLS}'),
});

new GuacamoleLite(
    { server: servidorHttps },
    { host: '127.0.0.1', port: 4822 },
    { crypt: { cypher: 'AES-256-CBC', key: chaveCompartilhada } }
);

servidorHttps.listen(PORTA, () => {
    console.log('Ponte RDP (guacamole-lite) ouvindo em :' + PORTA);
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
  echo "{\"success\":true,\"message\":\"Ponte RDP instalada e rodando na porta ${PORTA}.\"}"
else
  ULTIMO_LOG="$(journalctl -u rd-guac-bridge -n 20 --no-pager 2>/dev/null | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"Ponte RDP instalada mas o serviço não subiu. Log: ${ULTIMO_LOG}\"}"
fi
