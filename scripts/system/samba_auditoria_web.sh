#!/bin/bash
# samba_auditoria_web.sh <ativar|desativar|status>
#
# Liga/desliga a auditoria de arquivos dos compartilhamentos Samba via
# modulo VFS full_audit (renomear/mover, excluir, gravar conteudo), log
# saindo por syslog (facility LOCAL5, roteada por
# /etc/rsyslog.d/49-samba-audit.conf pra /var/log/samba/audit.log --
# ver setup_samba_auditoria.sh). Escreve/limpa /etc/samba/audit.conf
# (incluido pelo smb.conf global, ver
# App\Services\SambaGlobalConfigService::gerarSmbConf()) e recarrega o
# smbd.
#
# TUDO abaixo foi validado AO VIVO (nao "deveria funcionar segundo a
# documentacao") contra a versao real do Samba instalada, porque essa
# mesma tecnica (vfs objects customizado) ja derrubou o acesso Samba
# inteiro uma vez, com o modulo virusfilter -- ver
# antivirus_tempo_real_web.sh:
#
#   1. Os nomes de operacao do full_audit mudaram nas versoes recentes
#      do Samba (refatoracao pra sufixo "*at", estilo syscall Linux) --
#      "mkdir"/"rename"/"unlink"/"write" (nomes de tutoriais antigos)
#      NAO existem mais nesta versao (4.19.5-Ubuntu) e derrubam
#      QUALQUER tree connect ("Invalid success operations list" no log
#      do smbd) exatamente como o erro antigo do virusfilter. Os nomes
#      corretos, extraidos direto do binario instalado
#      (full_audit.so) e confirmados com um teste de verdade
#      (put/rename/del via smbclient): renameat, unlinkat, pwrite_recv.
#   2. Com a Lixeira Administrativa sempre ativa (recycle, ver
#      SambaTemplate::global()), um DELETE de usuario nunca chega como
#      "unlinkat" -- o recycle intercepta e faz um renameat pra dentro
#      de ".recycle/" antes. Por isso o parser em
#      SambaAuditoriaService::listar() trata "renameat" com destino
#      dentro de ".recycle/" como exclusao, nao como renomear/mover.
#      "unlinkat" continua na lista so pra cobrir uma exclusao
#      definitiva de algo que ja estava dentro da lixeira.
#   3. Uma escrita de arquivo (SMB2) dispara pwrite_send E pwrite_recv
#      -- so pwrite_recv entra na lista (recv = operacao concluida,
#      evita duplicar cada gravacao em duas linhas de log).
#   4. Copiar pasta/arquivo pela rede (Explorer, "scopy" no smbclient)
#      quase sempre usa Server-Side Copy (FSCTL_SRV_COPYCHUNK) em vez
#      de leitura+escrita normal -- confirmado ao vivo que isso NUNCA
#      aparece como pwrite, e sim como offload_write_recv. Criar pasta
#      tambem tem operacao propria (mkdirat). Sem os dois, uma copia de
#      pastas inteira (exatamente o caso que motivou essa investigacao)
#      passava 100% em branco pela auditoria, mesmo com tudo configurado
#      certo. IMPORTANTE: full_audit nao consegue resolver o nome do
#      arquivo pra offload_write (send OU recv) -- confirmado ao vivo,
#      o campo de caminho vem sempre vazio nessa operacao especifica
#      (limitacao do proprio modulo, nao tem como contornar via config).
#      SambaAuditoriaService::parsear() troca esse vazio por um texto
#      explicando a limitacao, em vez de mostrar uma celula em branco
#      sem explicacao nenhuma.
#   4. "vfs objects" nao acumula entre [global] e um include -- igual
#      antivirus_tempo_real_web.sh, repete "acl_xattr recycle" (base
#      do SambaTemplate::global()) e, se o antivirus em tempo real
#      estiver ativo agora (antivirus.conf nao vazio), repete
#      "virusfilter" + as mesmas diretivas dele tambem, senao ativar
#      auditoria desligaria o antivirus sem querer (audit.conf e
#      incluido DEPOIS de antivirus.conf no smb.conf, entao o valor
#      daqui e que vale).
#   5. O arquivo de log gerado por syslog precisa ser dono syslog:adm
#      (mesmo esquema de /var/log/auth.log) -- criado root:root (ou
#      inexistente) faz o rsyslog aceitar a mensagem em silencio e
#      NUNCA escrever no arquivo, sem erro nenhum visivel (ver
#      setup_samba_auditoria.sh, que cria com o dono certo).

set -u

ACAO="${1:-}"
ARQUIVO="/etc/samba/audit.conf"
ARQUIVO_AV="/etc/samba/antivirus.conf"

if [ "$ACAO" = "status" ]; then
  if [ -s "$ARQUIVO" ]; then
    echo "1"
  else
    echo "0"
  fi
  exit 0
fi

if [ "$ACAO" != "ativar" ] && [ "$ACAO" != "desativar" ]; then
  echo '{"success":false,"message":"Acao invalida."}'
  exit 1
fi

if [ "$ACAO" = "desativar" ]; then
  : > "$ARQUIVO"
  systemctl reload smbd 2>/dev/null || systemctl restart smbd
  echo '{"success":true,"message":"Auditoria de arquivos desativada."}'
  exit 0
fi

VFS_OBJECTS="acl_xattr recycle full_audit"
VIRUSFILTER_DIRETIVAS=""
if [ -s "$ARQUIVO_AV" ]; then
  VFS_OBJECTS="${VFS_OBJECTS} virusfilter"
  VIRUSFILTER_DIRETIVAS=$'virusfilter:scanner = clamav\nvirusfilter:socket path = /var/run/clamav/clamd.ctl\nvirusfilter:scan on open = yes\nvirusfilter:scan on close = no\nvirusfilter:max file size = 100000000\nvirusfilter:infected file action = quarantine\nvirusfilter:quarantine directory = /var/quarantine/rd-intranet\nvirusfilter:quarantine prefix = virus-'
fi

{
  echo "# Gerado pela RD Intranet (Samba > Auditoria de Arquivos). Nao edite manualmente."
  echo "vfs objects = ${VFS_OBJECTS}"
  echo "full_audit:prefix = %u|%I|%m|%S"
  echo "full_audit:success = renameat unlinkat pwrite_recv mkdirat offload_write_recv"
  echo "full_audit:failure = none"
  echo "full_audit:facility = LOCAL5"
  echo "full_audit:priority = NOTICE"
  if [ -n "$VIRUSFILTER_DIRETIVAS" ]; then
    echo "$VIRUSFILTER_DIRETIVAS"
  fi
} > "$ARQUIVO"

if ! testparm -s >/dev/null 2>&1; then
  : > "$ARQUIVO"
  echo '{"success":false,"message":"Configuracao gerada invalida, revertido. Confira se o modulo full_audit esta instalado (pacote samba-vfs-modules)."}'
  exit 1
fi

systemctl reload smbd 2>/dev/null || systemctl restart smbd

echo '{"success":true,"message":"Auditoria de arquivos ativada -- renomear/mover, excluir e gravar conteudo passam a ficar registrados."}'
