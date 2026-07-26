#!/bin/bash
# guacd_instalar_web.sh
# Instala o guacd (daemon que traduz RDP/VNC/SSH pro protocolo Guacamole --
# https://guacamole.apache.org/), parte do suporte a "RDP pelo navegador"
# em Ativos > ficha da máquina. Pacote oficial do Ubuntu (universe),
# travado na 1.3.0 -- suficiente pra RDP básico; se algum host exigir
# recurso só disponível em versão mais nova, o caminho é compilar da
# fonte, não feito aqui de propósito (mantém o script simples).
#
# Sem autenticação/banco próprio -- só traduz protocolo. Nunca deve
# escutar em interface pública (só quem fala com ele é a ponte
# guacamole-lite, rodando no mesmo servidor).

set -u

export DEBIAN_FRONTEND=noninteractive

if ! command -v guacd >/dev/null 2>&1; then
  if ! apt-get install -y -qq guacd >/tmp/rd_guacd_out_$$ 2>/tmp/rd_guacd_err_$$; then
    ERRO="$(tail -20 /tmp/rd_guacd_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_guacd_out_$$ /tmp/rd_guacd_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar o guacd: ${ERRO}\"}"
    exit 1
  fi
  rm -f /tmp/rd_guacd_out_$$ /tmp/rd_guacd_err_$$
fi

mkdir -p /etc/guacamole

# Bind explícito em localhost -- o pacote já vem assim por padrão, mas
# grava mesmo assim pra não depender do default do pacote silenciosamente
# mudar em uma atualização futura. Só escreve se ainda não existir, pra
# não sobrescrever um ajuste manual que alguém tenha feito.
if [ ! -f /etc/guacamole/guacd.conf ]; then
  cat > /etc/guacamole/guacd.conf <<'EOF'
[server]
bind_host = 127.0.0.1
bind_port = 4822
EOF
fi

systemctl enable --now guacd >/dev/null 2>&1
sleep 1

if systemctl is-active --quiet guacd; then
  echo '{"success":true,"message":"guacd instalado e rodando (127.0.0.1:4822)."}'
else
  ULTIMO_LOG="$(journalctl -u guacd -n 15 --no-pager 2>/dev/null | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"guacd instalado mas o serviço não subiu. Log: ${ULTIMO_LOG}\"}"
fi
