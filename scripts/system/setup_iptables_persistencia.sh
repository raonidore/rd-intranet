#!/bin/bash
# setup_iptables_persistencia.sh
# Passo de instalacao (rodar uma vez, como root: sudo ./setup_iptables_persistencia.sh).
# NAO tem sufixo _web.sh de proposito -- fora da regra de sudo NOPASSWD do
# www-data, mesmo criterio ja usado em setup_rotas_extras.sh/setup_acl_admin.sh.
#
# Cria a estrutura de diretorios usada pelo backup/rollback do firewall
# gerenciado pela tela web, e o servico que reaplica o ultimo ruleset
# CONFIRMADO (/etc/iptables/rd-intranet.rules.v4) no boot -- so mexe nas
# tabelas filter/nat, nunca sobrescreve regras de outras ferramentas fora
# desse arquivo.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

mkdir -p /etc/iptables
mkdir -p /etc/rd-intranet/.iptables-backups
touch /etc/iptables/rd-intranet.rules.v4

cat > /etc/systemd/system/rd-iptables-restore.service <<'EOF'
[Unit]
Description=RD Intranet - restaura o firewall (iptables) gerenciado pela intranet
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/bin/bash -c 'test -s /etc/iptables/rd-intranet.rules.v4 && /usr/sbin/iptables-restore /etc/iptables/rd-intranet.rules.v4 || true'
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

chmod 644 /etc/systemd/system/rd-iptables-restore.service

systemctl daemon-reload
systemctl enable rd-iptables-restore.service >/dev/null 2>&1

echo "OK"
