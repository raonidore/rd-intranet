#!/bin/bash
# setup_samba_base.sh <repo_dir>
# Passo de instalacao (rodar uma vez, como root: sudo ./setup_samba_base.sh /var/www/rd.intranet).
# NAO tem sufixo _web.sh de proposito -- mexe direto no smb.conf do
# sistema, fora da regra de sudo NOPASSWD do www-data.
#
# Prepara a base que os scripts *_web.sh chamados pela tela web ja
# assumem que existe: o smb.conf com o [global] padrao da RD Intranet
# (mesmo template de App\Core\Samba\SambaTemplate::global(), usado
# tambem pela tela Samba > Config. Global -- fonte unica, nao duplicada
# aqui) + 'include = /etc/samba/shares.conf', o proprio shares.conf
# (comeca vazio, a tela de Compartilhamentos que preenche depois), o
# diretorio de backup dos deploys de compartilhamentos e o diretorio de
# tmp onde o PHP (www-data) escreve o shares.conf gerado antes do script
# root aplicar (App\Core\Samba\SambaConfigWriter). Sem isso, a tela de
# Deploy > Aplicar Samba falha ("Arquivo temporário não encontrado" ou
# NT_STATUS_BAD_NETWORK_NAME) e nenhum compartilhamento fica de fato
# acessivel via rede.
#
# Idempotente: so mexe no que ainda nao existe.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

REPO_DIR="${1:?uso: setup_samba_base.sh <repo_dir>}"

mkdir -p /etc/samba/rd/backups

mkdir -p /etc/samba/rd/tmp
chown root:www-data /etc/samba/rd/tmp
chmod 775 /etc/samba/rd/tmp

if [ ! -f /etc/samba/shares.conf ]; then
  printf '# Arquivo gerado pela RD Intranet\n# Nao edite manualmente. Altere pela interface web.\n' > /etc/samba/shares.conf
  echo "Criado /etc/samba/shares.conf (vazio)."
fi

if [ ! -f /etc/samba/smb.conf ] || ! grep -q "^include = /etc/samba/shares.conf" /etc/samba/smb.conf; then
  GLOBAL=$(php -r 'require $argv[1] . "/vendor/autoload.php"; echo App\Core\Samba\SambaTemplate::global();' "$REPO_DIR")

  {
    echo "$GLOBAL"
    echo "include = /etc/samba/shares.conf"
  } > /tmp/smb.conf.rd-intranet

  if [ -f /etc/samba/smb.conf ]; then
    cp /etc/samba/smb.conf "/etc/samba/smb.conf.bkp.$(date +%Y%m%d%H%M%S)"
  fi
  mv /tmp/smb.conf.rd-intranet /etc/samba/smb.conf

  echo "smb.conf [global] + include gerados."
else
  echo "smb.conf ja tem include de shares.conf, nada a fazer."
fi

testparm -s >/dev/null
systemctl reload smbd 2>/dev/null || systemctl restart smbd

echo "OK"
