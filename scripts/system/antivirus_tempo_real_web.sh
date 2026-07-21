#!/bin/bash
# antivirus_tempo_real_web.sh <ativar|desativar>
# Liga/desliga o escaneamento em tempo real (on-access) dos
# compartilhamentos Samba via modulo VFS virusfilter + ClamAV. Escreve/limpa
# /etc/samba/antivirus.conf (incluido pelo smb.conf global, ver
# App\Core\Samba\SambaTemplate::global()) e recarrega o smbd.
#
# IMPORTANTE: "vfs objects" nao acumula entre [global] e um arquivo
# incluido depois -- o valor daqui *substitui* o do smb.conf principal, em
# vez de somar. Por isso repete "acl_xattr recycle" (o que
# SambaTemplate::global() ja define) junto com "virusfilter" -- senao
# ativar o antivirus desligaria ACL e lixeira sem querer.

set -u

ACAO="${1:-}"
ARQUIVO="/etc/samba/antivirus.conf"

if [ "$ACAO" != "ativar" ] && [ "$ACAO" != "desativar" ]; then
  echo '{"success":false,"message":"Acao invalida."}'
  exit 1
fi

if [ "$ACAO" = "desativar" ]; then
  : > "$ARQUIVO"
  systemctl reload smbd 2>/dev/null || systemctl restart smbd
  echo '{"success":true,"message":"Escaneamento em tempo real desativado."}'
  exit 0
fi

if ! command -v clamdscan >/dev/null 2>&1; then
  echo '{"success":false,"message":"Instale o ClamAV antes de ativar o tempo real."}'
  exit 1
fi

mkdir -p /var/quarantine/rd-intranet

cat > "$ARQUIVO" <<'EOF'
# Gerado pela RD Intranet (Seguranca > Antivirus). Nao edite manualmente.
#
# Nomes dos parametros "virusfilter:*" usam ESPACO entre palavras, nao
# hifen (confirmado no man vfs_virusfilter oficial) -- uma versao anterior
# deste script usava hifen em tudo (ex: "clamav-socket-path",
# "scan-on-open"), o que o modulo nao reconhecia. Sem "virusfilter:scanner"
# valido, o proprio connect() do modulo falhava, derrubando QUALQUER tree
# connect na hora de abrir um compartilhamento (nao so o escaneamento)
# -- por isso ligar "tempo real" quebrava o acesso Samba inteiro.
vfs objects = acl_xattr recycle virusfilter
virusfilter:scanner = clamav
virusfilter:socket path = /var/run/clamav/clamd.ctl
virusfilter:scan on open = yes
virusfilter:scan on close = no
virusfilter:max file size = 100000000
virusfilter:infected file action = quarantine
virusfilter:quarantine directory = /var/quarantine/rd-intranet
virusfilter:quarantine prefix = virus-
EOF

if ! testparm -s >/dev/null 2>&1; then
  : > "$ARQUIVO"
  echo '{"success":false,"message":"Configuracao gerada invalida, revertido. Confira se o modulo virusfilter esta instalado (pacote samba-vfs-modules)."}'
  exit 1
fi

systemctl reload smbd 2>/dev/null || systemctl restart smbd

echo '{"success":true,"message":"Escaneamento em tempo real ativado -- arquivos infectados sao movidos para quarentena ao serem abertos."}'
