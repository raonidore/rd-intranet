#!/bin/bash
# cloudflared_web.sh <acao>
# Instala e gerencia o cloudflared (Cloudflare Tunnel) neste servidor --
# expoe esta intranet publicamente atraves da rede da Cloudflare, sem
# abrir porta nenhuma de entrada. Instalacao explicita via repositorio
# apt oficial (baixa chave GPG e escreve o sources.list.d na mao, nunca
# "curl | sh" -- nenhum script deste repo instala as cegas via pipe).
#
# Acoes:
#   instalar            adiciona o repo oficial + apt-get install
#   servico_instalar    le o connector token do STDIN, instala como servico systemd
#   servico_remover     desinstala o servico systemd

set -u

ACAO="${1:-}"

CHAVEIRO="/usr/share/keyrings/cloudflare-main.gpg"
REPO_ARQUIVO="/etc/apt/sources.list.d/cloudflared.list"

instalar() {
  if command -v cloudflared >/dev/null 2>&1; then
    echo '{"success":true,"message":"cloudflared já estava instalado."}'
    return 0
  fi

  export DEBIAN_FRONTEND=noninteractive

  if ! apt-get install -y -qq gnupg ca-certificates curl >/tmp/rd_cf_out_$$ 2>/tmp/rd_cf_err_$$; then
    ERRO="$(tail -10 /tmp/rd_cf_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_cf_out_$$ /tmp/rd_cf_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar pre-requisitos: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_cf_out_$$ /tmp/rd_cf_err_$$

  mkdir -p --mode=0755 /usr/share/keyrings

  if ! curl -fsSL "https://pkg.cloudflare.com/cloudflare-main.gpg" -o "$CHAVEIRO" 2>/tmp/rd_cf_err_$$; then
    ERRO="$(tail -10 /tmp/rd_cf_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_cf_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao baixar a chave GPG da Cloudflare: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_cf_err_$$
  chmod 644 "$CHAVEIRO"

  # Ao contrario do Tailscale, a Cloudflare nao publica um .list pronto
  # por codinome -- a linha "deb" e escrita na mao aqui, mesma cautela do
  # speedtest_instalar_web.sh: so usa o codinome real da maquina se ele
  # de fato tiver Release publicado, senao cai pro ultimo LTS conhecido.
  CODINOME="$(. /etc/os-release && echo "$VERSION_CODENAME")"
  CODINOME_FALLBACK="noble"

  if ! curl -fsS -o /dev/null "https://pkg.cloudflare.com/cloudflared/dists/${CODINOME}/Release" 2>/dev/null; then
    CODINOME="$CODINOME_FALLBACK"
  fi

  echo "deb [signed-by=${CHAVEIRO}] https://pkg.cloudflare.com/cloudflared ${CODINOME} main" \
    | tee "$REPO_ARQUIVO" >/dev/null

  if ! apt-get update -qq >/tmp/rd_cf_out_$$ 2>/tmp/rd_cf_err_$$; then
    ERRO="$(tail -10 /tmp/rd_cf_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_cf_out_$$ /tmp/rd_cf_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao atualizar lista de pacotes após adicionar o repositório da Cloudflare: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_cf_out_$$ /tmp/rd_cf_err_$$

  if ! apt-get install -y -qq cloudflared >/tmp/rd_cf_out_$$ 2>/tmp/rd_cf_err_$$; then
    ERRO="$(tail -20 /tmp/rd_cf_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_cf_out_$$ /tmp/rd_cf_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar o cloudflared: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_cf_out_$$ /tmp/rd_cf_err_$$

  echo '{"success":true,"message":"cloudflared instalado com sucesso."}'
}

servico_instalar() {
  if ! command -v cloudflared >/dev/null 2>&1; then
    echo '{"success":false,"message":"cloudflared não está instalado."}'
    return 1
  fi

  # Connector token chega inteiro pelo STDIN (LinuxService::executarComEntrada)
  # -- nunca argv/disco do lado PHP. "cloudflared service install" nao tem
  # forma documentada de ler o token por stdin/arquivo, entao ele passa por
  # argv do PROCESSO FILHO "cloudflared" por uma fracao de segundo -- risco
  # residual aceito (limitacao do proprio binario, mesmo caso do authkey do
  # tailscale_web.sh), documentado no plano desta feature.
  local token
  token="$(cat -)"
  if [ -z "$token" ]; then
    echo '{"success":false,"message":"Token vazio."}'
    return 1
  fi

  # Ignora falha aqui de proposito -- cobre o caso de reinstalar o
  # servico apontando pra um tunel diferente (pode nao existir servico
  # anterior nenhum pra desinstalar).
  cloudflared service uninstall >/dev/null 2>&1 || true

  if cloudflared service install "$token" >/tmp/rd_cf_svc_$$ 2>&1; then
    rm -f /tmp/rd_cf_svc_$$
    echo '{"success":true,"message":"Serviço instalado e conectado."}'
  else
    ERRO="$(tail -10 /tmp/rd_cf_svc_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_cf_svc_$$
    echo "{\"success\":false,\"message\":\"Falha ao instalar o serviço: ${ERRO}\"}"
    return 1
  fi
}

servico_remover() {
  cloudflared service uninstall >/dev/null 2>&1 || true
  echo '{"success":true,"message":"Serviço removido."}'
}

case "$ACAO" in
  instalar) instalar ;;
  servico_instalar) servico_instalar ;;
  servico_remover) servico_remover ;;
  *)
    echo '{"success":false,"message":"Ação desconhecida."}'
    exit 1
    ;;
esac
