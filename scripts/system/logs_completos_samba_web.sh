#!/bin/bash
# logs_completos_samba_web.sh
# Reune os logs relevantes de Samba (daemon + por cliente) e do Apache
# (esta aplicacao) num unico lugar -- pra investigar incidentes (ex:
# conexao caindo no meio de uma operacao) direto pelo portal, sem
# precisar de acesso SSH ao servidor.

echo "### JOURNALCTL_SMBD"
journalctl -u smbd -n 500 --no-pager 2>/dev/null

echo ""
echo "### APACHE_ERROR"
if [ -f /var/log/apache2/rd.intranet_error.log ]; then
  tail -n 300 /var/log/apache2/rd.intranet_error.log
else
  echo "(arquivo nao encontrado)"
fi

echo ""
echo "### CORE_DUMPS"
# Se algum processo smbd/nmbd travou de verdade (crash), costuma deixar um
# core dump aqui -- presenca de arquivo recente aqui e evidencia forte de
# crash real (em vez de so a conexao ter sido fechada por timeout em outro
# lugar da cadeia, ex: PHP/Apache).
if [ -d /var/log/samba/cores ]; then
  find /var/log/samba/cores -type f -printf '%T@ %p (%s bytes)\n' 2>/dev/null | sort -rn | head -20
else
  echo "(pasta de core dumps nao encontrada)"
fi

echo ""
echo "### AUDITORIA_JOURNAL"
# Diagnostico da Auditoria de Arquivos (Samba > Auditoria): o modulo
# full_audit manda a mensagem por syslog (facility LOCAL5, tag
# "smbd_audit") -- ela chega no journal SEMPRE, independente do
# roteamento pro arquivo proprio ter dado certo ou nao (ver
# setup_samba_auditoria.sh). Comparar isto com AUDITORIA_ARQUIVO logo
# abaixo aponta exatamente onde esta o problema quando "nao aparece
# nada": se aqui tambem estiver vazio, o full_audit nao esta nem
# tentando logar aquela operacao (nome de operacao fora da lista
# configurada, ex: offload_write em copia server-side, ou auditoria
# desligada); se aqui tiver entrada mas o arquivo abaixo estiver vazio,
# o problema e so o roteamento rsyslog->arquivo (dono/permissao).
journalctl -t smbd_audit -n 200 --no-pager 2>/dev/null

echo ""
echo "### AUDITORIA_ARQUIVO"
if [ -f /var/log/samba/audit.log ]; then
  tail -n 200 /var/log/samba/audit.log
else
  echo "(arquivo nao encontrado)"
fi

echo ""
echo "### LOGS_POR_CLIENTE"
if [ -d /var/log/samba ]; then
  for arquivo in /var/log/samba/*.log /var/log/samba/log.*; do
    [ -f "$arquivo" ] || continue
    echo "--- $arquivo ---"
    tail -n 100 "$arquivo" 2>/dev/null
    echo ""
  done
else
  echo "(pasta /var/log/samba nao encontrada)"
fi
