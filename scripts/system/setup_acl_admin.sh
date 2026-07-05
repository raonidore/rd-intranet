#!/bin/bash
# setup_acl_admin.sh
# Passo de instalacao (rodar uma vez, como root: sudo ./setup_acl_admin.sh).
# NAO tem sufixo _web.sh de proposito -- nao fica coberto pela regra de sudo
# NOPASSWD do www-data (/opt/rdtecnologia/scripts/*_web.sh). Criar conta
# privilegiada e conceder direitos administrativos deve ser sempre uma
# acao manual de um operador root, nunca algo que a aplicacao web possa
# disparar sozinha.
#
# Cria a conta de servico Samba (sem shell/login Unix real) usada pelos
# scripts *_web.sh para aplicar ACL por usuario em compartilhamentos
# (ver aplicar_acl_compartilhamento_web.sh). Idempotente: pode ser
# executado de novo sem duplicar nada.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

CONTA="svc_acl_admin"
AUTHFILE="/opt/rdtecnologia/scripts/.smbacl_auth"

if ! id "$CONTA" >/dev/null 2>&1; then
  useradd --system --no-create-home --shell /usr/sbin/nologin "$CONTA"
  echo "Usuário de sistema $CONTA criado."
else
  echo "Usuário de sistema $CONTA já existe."
fi

if [ ! -f "$AUTHFILE" ]; then
  SENHA=$(openssl rand -base64 24)

  printf "%s\n%s\n" "$SENHA" "$SENHA" | smbpasswd -s -a "$CONTA"
  smbpasswd -e "$CONTA"

  umask 077
  {
    echo "username = $CONTA"
    echo "password = $SENHA"
    echo "domain = WORKGROUP"
  } > "$AUTHFILE"

  chown root:root "$AUTHFILE"
  chmod 600 "$AUTHFILE"

  unset SENHA

  echo "Conta Samba $CONTA criada e authfile gerado em $AUTHFILE."
else
  echo "Authfile já existe em $AUTHFILE, não vou gerar senha nova."
fi

if net sam rights list SeDiskOperatorPrivilege 2>/dev/null | grep -qi "$CONTA"; then
  echo "$CONTA já tem SeDiskOperatorPrivilege."
else
  net sam rights grant "$CONTA" SeDiskOperatorPrivilege
  echo "SeDiskOperatorPrivilege concedido a $CONTA."
fi

echo "OK"
