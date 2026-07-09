#!/bin/bash
# dependencias_status_web.sh
# Somente leitura: verifica se cada ferramenta que a RD Intranet usa esta
# instalada. Lista de comandos fixa aqui (nao vem de fora).

declare -A COMANDOS=(
  [iptables]="iptables"
  [conntrack]="conntrack"
  [openssl]="openssl"
  [certbot]="certbot"
  [cron]="crontab"
  [lm-sensors]="sensors"
  [mariadb-client]="mysql"
  [samba]="smbd"
  [iproute2]="ip"
  [python3]="python3"
  [smbclient]="smbcacls"
  [apache2]="apache2ctl"
  [openssh-server]="sshd"
  [netplan]="netplan"
  [samba-common-bin]="smbpasswd"
  [acl]="getfacl"
  [traceroute]="traceroute"
  [iputils-ping]="ping"
)

for CHAVE in "${!COMANDOS[@]}"; do
  if command -v "${COMANDOS[$CHAVE]}" >/dev/null 2>&1; then
    echo "${CHAVE}|1"
  else
    echo "${CHAVE}|0"
  fi
done
