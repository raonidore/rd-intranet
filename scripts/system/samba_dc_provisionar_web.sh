#!/bin/bash
# samba_dc_provisionar_web.sh <execucao_id> <realm> <workgroup> <dns_backend>
#
# Promove este servidor a Active Directory Domain Controller via
# "samba-tool domain provision". Roda em segundo plano
# (LinuxService::executarScriptEmSegundoPlanoComEntrada) porque pode levar
# minutos -- escreve o proprio progresso em storage/samba_dc_status/
# <execucao_id>.json, no mesmo formato que lote_arquivos_samba_web.sh ja
# usa (status/percentual/mensagem), consultado por polling pelo PHP.
#
# A senha do Administrator do dominio chega pelo STDIN deste processo
# (nunca por argv/"ps aux") -- lida com "read" logo abaixo e repassada pro
# samba-tool tambem via stdin (duas vezes, senha+confirmacao), nunca pela
# flag --adminpass (que apareceria em "ps aux" enquanto o samba-tool roda).
#
# Faz backup do smb.conf atual ANTES de provisionar, no MESMO caminho/
# formato que apply_smb_conf_web.sh ja usa (/etc/samba/smb.conf.bkp.
# <timestamp>, direto em /etc/samba/, confirmado por leitura direta do
# script) -- assim ele aparece automaticamente na tela de restauracao de
# backup ja existente (Samba > Configuracao Global) se algo der errado.
#
# LIMITACAO CONHECIDA: se o samba-tool ja tiver criado
# /var/lib/samba/private/sam.ldb antes de falhar (falha tardia), o
# rollback automatico abaixo so cobre o smb.conf -- limpar
# /var/lib/samba/private/* numa falha tardia e manual. Isso fica
# documentado tambem na mensagem de erro final.

set -u

STATUS_DIR="/var/www/rd.intranet/storage/samba_dc_status"
mkdir -p "$STATUS_DIR"

EXECUCAO_ID="$1"
REALM="$2"
WORKGROUP="$3"
DNS_BACKEND="$4"

STATUS_FILE="$STATUS_DIR/${EXECUCAO_ID}.json"

escrever_status() {
  local status="$1" etapa="$2" totalEtapas="$3" mensagem="$4"
  php -r '
    $status = $argv[1];
    $etapa = (int)$argv[2];
    $totalEtapas = (int)$argv[3];
    $mensagem = $argv[4];
    $pct = $totalEtapas > 0 ? (int)round(($etapa / $totalEtapas) * 100) : 100;
    echo json_encode([
        "status" => $status,
        "etapa" => $etapa,
        "total_etapas" => $totalEtapas,
        "percentual" => min(100, max(0, $pct)),
        "mensagem" => $mensagem,
        "atualizado_em" => time(),
    ]);
  ' -- "$status" "$etapa" "$totalEtapas" "$mensagem" > "$STATUS_FILE"
  chmod 644 "$STATUS_FILE"
}

TOTAL_ETAPAS=7

if [[ ! "$EXECUCAO_ID" =~ ^[a-f0-9]+$ ]]; then
  echo "ID de execucao invalido" >&2
  exit 1
fi

if [[ ! "$REALM" =~ ^[A-Z0-9]+(\.[A-Z0-9]+)+$ ]]; then
  escrever_status "erro" 0 "$TOTAL_ETAPAS" "Realm invalido."
  exit 1
fi

if [[ ! "$WORKGROUP" =~ ^[A-Z0-9-]{1,15}$ ]]; then
  escrever_status "erro" 0 "$TOTAL_ETAPAS" "Workgroup invalido."
  exit 1
fi

if [ "$DNS_BACKEND" != "SAMBA_INTERNAL" ]; then
  escrever_status "erro" 0 "$TOTAL_ETAPAS" "Backend de DNS nao suportado nesta versao."
  exit 1
fi

# Nunca confiar apenas na checagem que o PHP ja fez -- redundancia de
# seguranca contra um provisionamento em cima de um DC ja existente.
if [ -f /var/lib/samba/private/sam.ldb ]; then
  escrever_status "erro" 0 "$TOTAL_ETAPAS" "Servidor ja e um Controlador de Dominio."
  exit 1
fi

read -r SENHA_ADMIN

if [ -z "$SENHA_ADMIN" ]; then
  escrever_status "erro" 0 "$TOTAL_ETAPAS" "Senha do Administrator nao informada."
  exit 1
fi

# ── Etapa 1: backup do smb.conf atual ───────────────────────────────────
escrever_status "rodando" 1 "$TOTAL_ETAPAS" "Fazendo backup da configuração atual..."
BACKUP="/etc/samba/smb.conf.bkp.$(date +%Y%m%d%H%M%S)"
if ! cp /etc/samba/smb.conf "$BACKUP" 2>/dev/null; then
  escrever_status "erro" 1 "$TOTAL_ETAPAS" "Falha ao criar backup do smb.conf -- provisionamento abortado antes de qualquer mudança."
  exit 1
fi

restaurar_e_sair() {
  local etapa="$1" mensagem="$2"
  cp "$BACKUP" /etc/samba/smb.conf 2>/dev/null
  systemctl start smbd nmbd >/dev/null 2>&1
  escrever_status "erro" "$etapa" "$TOTAL_ETAPAS" "$mensagem (configuração original restaurada, smbd/nmbd reiniciados)."
  exit 1
}

# ── Etapa 2: parar smbd/nmbd (samba-tool precisa das portas livres) ─────
escrever_status "rodando" 2 "$TOTAL_ETAPAS" "Parando smbd/nmbd..."
systemctl stop smbd nmbd >/dev/null 2>&1

# ── Etapa 3: samba-tool domain provision ────────────────────────────────
escrever_status "rodando" 3 "$TOTAL_ETAPAS" "Executando samba-tool domain provision (pode levar alguns minutos)..."
# "printf" aqui e' o BUILTIN do bash (nao /usr/bin/printf) -- nao cria um
# processo novo, entao a senha nunca aparece como argv de nenhuma linha
# em "ps aux"/"/proc/<pid>/cmdline" em nenhum momento, nem que seja por
# um instante. So samba-tool (que le do stdin, nunca recebe a senha como
# argumento seu) e' um processo de verdade aqui.
SAIDA_PROVISION="/tmp/rd_dc_provision_$$"
if ! printf '%s\n%s\n' "$SENHA_ADMIN" "$SENHA_ADMIN" | samba-tool domain provision \
      --realm="$REALM" --domain="$WORKGROUP" --server-role=dc \
      --dns-backend="$DNS_BACKEND" \
      >"$SAIDA_PROVISION" 2>&1; then
  ERRO=$(tail -20 "$SAIDA_PROVISION" | tr '\n' ' ' | sed 's/"/\\"/g')
  rm -f "$SAIDA_PROVISION"
  restaurar_e_sair 3 "Falha no samba-tool domain provision: ${ERRO}. Se /var/lib/samba/private/sam.ldb tiver sido criado, limpeza manual é necessária antes de tentar novamente."
fi
rm -f "$SAIDA_PROVISION"
SENHA_ADMIN=""

# ── Etapa 4: reinserir includes de shares.conf/antivirus.conf ──────────
escrever_status "rodando" 4 "$TOTAL_ETAPAS" "Restaurando compartilhamentos e integração de antivírus..."
if ! grep -q '^\s*include\s*=\s*/etc/samba/shares.conf' /etc/samba/smb.conf; then
  sed -i '/^\[global\]/a\	include = /etc/samba/antivirus.conf\n	include = /etc/samba/shares.conf' /etc/samba/smb.conf
fi

# ── Etapa 5: krb5.conf ───────────────────────────────────────────────────
escrever_status "rodando" 5 "$TOTAL_ETAPAS" "Aplicando configuração Kerberos..."
if [ -f /var/lib/samba/private/krb5.conf ]; then
  [ -f /etc/krb5.conf ] && cp /etc/krb5.conf "/etc/krb5.conf.bkp.$(date +%Y%m%d%H%M%S)" 2>/dev/null
  cp /var/lib/samba/private/krb5.conf /etc/krb5.conf
fi

# ── Etapa 6: validar smb.conf resultante ────────────────────────────────
escrever_status "rodando" 6 "$TOTAL_ETAPAS" "Validando configuração..."
if ! testparm -s /etc/samba/smb.conf >/dev/null 2>&1; then
  restaurar_e_sair 6 "smb.conf inválido após promoção (testparm falhou)."
fi

# ── Etapa 7: trocar serviços e confirmar ────────────────────────────────
escrever_status "rodando" 7 "$TOTAL_ETAPAS" "Ativando o serviço do Controlador de Domínio..."
systemctl disable --now smbd nmbd >/dev/null 2>&1
systemctl unmask samba-ad-dc >/dev/null 2>&1
systemctl enable --now samba-ad-dc >/dev/null 2>&1

if systemctl is-active --quiet samba-ad-dc; then
  escrever_status "concluido" "$TOTAL_ETAPAS" "$TOTAL_ETAPAS" "Servidor promovido a Controlador de Domínio (realm: ${REALM}, domínio: ${WORKGROUP})."
  exit 0
fi

LOG=$(journalctl -u samba-ad-dc -n 30 --no-pager 2>/dev/null | tail -20 | tr '\n' ' ' | sed 's/"/\\"/g')
escrever_status "erro" 7 "$TOTAL_ETAPAS" "samba-ad-dc não iniciou após a promoção. Log: ${LOG}. O smb.conf já foi promovido (não houve rollback automático nesta etapa) -- verifique manualmente."
exit 1
