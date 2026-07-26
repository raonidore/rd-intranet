#!/bin/bash
# tailscale_web.sh <acao> [hostname]
# Instala e gerencia o Tailscale (VPN mesh) neste servidor -- acesso
# privado direto de qualquer dispositivo autorizado, sem depender de VPN
# de terceiros. Instalacao explicita via repositorio apt oficial (baixa
# chave GPG e lista do repo com curl -o, nunca "curl | sh" -- nenhum
# script deste repo instala as cegas via pipe, mesmo criterio de
# speedtest_instalar_web.sh).
#
# Acoes:
#   instalar               adiciona o repo oficial + apt-get install + habilita tailscaled
#   conectar [hostname]    le o authkey do STDIN (nunca argv/disco), roda "tailscale up"
#   desconectar [--logout] tailscale down (ou logout, remove de vez do tailnet)
#   status                 tailscale status --json (estado sintetico se nao instalado)

set -u

ACAO="${1:-}"

CHAVEIRO="/usr/share/keyrings/tailscale-archive-keyring.gpg"
REPO_ARQUIVO="/etc/apt/sources.list.d/tailscale.list"

instalar() {
  if command -v tailscale >/dev/null 2>&1; then
    systemctl enable --now tailscaled >/dev/null 2>&1
    echo '{"success":true,"message":"Tailscale já estava instalado."}'
    return 0
  fi

  export DEBIAN_FRONTEND=noninteractive

  if ! apt-get install -y -qq gnupg ca-certificates curl >/tmp/rd_ts_out_$$ 2>/tmp/rd_ts_err_$$; then
    ERRO="$(tail -10 /tmp/rd_ts_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_ts_out_$$ /tmp/rd_ts_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar pre-requisitos: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_ts_out_$$ /tmp/rd_ts_err_$$

  mkdir -p --mode=0755 /usr/share/keyrings

  # Tailscale publica um .list ja pronto por codinome de distro -- so
  # cai pro fallback (ultimo LTS conhecido) se o codinome real da
  # maquina ainda nao tiver build publicado (mesma cautela ja
  # confirmada em producao no speedtest_instalar_web.sh pro pacote da
  # Ookla).
  CODINOME="$(. /etc/os-release && echo "$VERSION_CODENAME")"
  CODINOME_FALLBACK="noble"

  if ! curl -fsS -o /dev/null "https://pkgs.tailscale.com/stable/ubuntu/${CODINOME}.tailscale-keyring.list" 2>/dev/null; then
    CODINOME="$CODINOME_FALLBACK"
  fi

  if ! curl -fsSL "https://pkgs.tailscale.com/stable/ubuntu/${CODINOME}.noarmor.gpg" -o "$CHAVEIRO" 2>/tmp/rd_ts_err_$$; then
    ERRO="$(tail -10 /tmp/rd_ts_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_ts_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao baixar a chave GPG do Tailscale: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_ts_err_$$
  chmod 644 "$CHAVEIRO"

  if ! curl -fsSL "https://pkgs.tailscale.com/stable/ubuntu/${CODINOME}.tailscale-keyring.list" -o "$REPO_ARQUIVO" 2>/tmp/rd_ts_err_$$; then
    ERRO="$(tail -10 /tmp/rd_ts_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_ts_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao baixar a lista do repositório do Tailscale: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_ts_err_$$

  # "-qq" so silencia o progresso do apt -- o dpkg continua imprimindo
  # "Unpacking.../Setting up..." no stdout mesmo em modo nao interativo,
  # o que quebra o json_decode() do lado PHP (mesmo bug ja corrigido em
  # dependencias_instalar_web.sh/speedtest_instalar_web.sh).
  if ! apt-get update -qq >/tmp/rd_ts_out_$$ 2>/tmp/rd_ts_err_$$; then
    ERRO="$(tail -10 /tmp/rd_ts_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_ts_out_$$ /tmp/rd_ts_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao atualizar lista de pacotes após adicionar o repositório do Tailscale: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_ts_out_$$ /tmp/rd_ts_err_$$

  if ! apt-get install -y -qq tailscale >/tmp/rd_ts_out_$$ 2>/tmp/rd_ts_err_$$; then
    ERRO="$(tail -20 /tmp/rd_ts_err_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_ts_out_$$ /tmp/rd_ts_err_$$
    echo "{\"success\":false,\"message\":\"Erro ao instalar o Tailscale: ${ERRO}\"}"
    return 1
  fi
  rm -f /tmp/rd_ts_out_$$ /tmp/rd_ts_err_$$

  systemctl enable --now tailscaled >/dev/null 2>&1

  echo '{"success":true,"message":"Tailscale instalado com sucesso."}'
}

conectar() {
  if ! command -v tailscale >/dev/null 2>&1; then
    echo '{"success":false,"message":"Tailscale não está instalado."}'
    return 1
  fi

  local hostname_desejado="${1:-}"
  if [ -n "$hostname_desejado" ] && ! [[ "$hostname_desejado" =~ ^[a-zA-Z0-9.-]{1,63}$ ]]; then
    echo '{"success":false,"message":"Hostname inválido."}'
    return 1
  fi

  # Authkey chega inteiro pelo STDIN (LinuxService::executarComEntrada) --
  # nunca argv/disco do lado PHP. O "tailscale up" em si nao tem forma
  # documentada de ler o authkey por stdin/arquivo (confirmado: issue
  # aberta no tracker oficial do projeto), entao ele acaba passando por
  # argv do PROCESSO FILHO "tailscale" por uma fracao de segundo -- risco
  # residual aceito, documentado no plano desta feature, sem alternativa
  # de verdade sem mudanca no proprio binario.
  local authkey
  authkey="$(cat -)"
  if [ -z "$authkey" ]; then
    echo '{"success":false,"message":"Authkey vazio."}'
    return 1
  fi

  local args=(--authkey="$authkey" --accept-dns=false --reset)
  if [ -n "$hostname_desejado" ]; then
    args+=(--hostname="$hostname_desejado")
  fi

  if tailscale up "${args[@]}" >/tmp/rd_ts_up_$$ 2>&1; then
    rm -f /tmp/rd_ts_up_$$
    echo '{"success":true,"message":"Conectado ao tailnet."}'
  else
    ERRO="$(tail -10 /tmp/rd_ts_up_$$ | tr '\n' ' ' | sed 's/"/\\"/g')"
    rm -f /tmp/rd_ts_up_$$
    echo "{\"success\":false,\"message\":\"Falha ao conectar: ${ERRO}\"}"
    return 1
  fi
}

desconectar() {
  if ! command -v tailscale >/dev/null 2>&1; then
    echo '{"success":true,"message":"Tailscale não está instalado."}'
    return 0
  fi

  if [ "${1:-}" = "--logout" ]; then
    tailscale logout >/dev/null 2>&1
    echo '{"success":true,"message":"Desconectado e removido do tailnet."}'
  else
    tailscale down >/dev/null 2>&1
    echo '{"success":true,"message":"Desconectado."}'
  fi
}

status() {
  if ! command -v tailscale >/dev/null 2>&1; then
    echo '{"BackendState":"NotInstalled"}'
    return 0
  fi

  tailscale status --json 2>/dev/null || echo '{"BackendState":"Unknown"}'
}

case "$ACAO" in
  instalar) instalar ;;
  conectar) conectar "${2:-}" ;;
  desconectar) desconectar "${2:-}" ;;
  status) status ;;
  *)
    echo '{"success":false,"message":"Ação desconhecida."}'
    exit 1
    ;;
esac
