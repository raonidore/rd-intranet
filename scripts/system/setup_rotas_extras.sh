#!/bin/bash
# setup_rotas_extras.sh
# Passo de instalacao (rodar uma vez, como root: sudo ./setup_rotas_extras.sh).
# NAO tem sufixo _web.sh de proposito -- fora da regra de sudo NOPASSWD do
# www-data, mesmo criterio ja usado em setup_acl_admin.sh.
#
# Cria o mecanismo de persistencia de rotas extras, ISOLADO do netplan que
# ja gerencia IP de interface (evita as duas features sobrescreverem o
# mesmo arquivo YAML uma da outra). Estado em /etc/rd-intranet/rotas-extras.conf
# (uma rota por linha: "destino_cidr via gateway dev interface"), aplicado
# no boot por um servico systemd oneshot.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

mkdir -p /etc/rd-intranet
touch /etc/rd-intranet/rotas-extras.conf
chmod 644 /etc/rd-intranet/rotas-extras.conf

cat > /etc/systemd/system/rd-rotas-extras.service <<'EOF'
[Unit]
Description=RD Intranet - aplica rotas extras persistidas
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/bin/bash -c 'while read -r linha; do [ -z "$linha" ] && continue; ip route replace $linha; done < /etc/rd-intranet/rotas-extras.conf'
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

chmod 644 /etc/systemd/system/rd-rotas-extras.service

systemctl daemon-reload
systemctl enable rd-rotas-extras.service >/dev/null 2>&1

echo "OK"
