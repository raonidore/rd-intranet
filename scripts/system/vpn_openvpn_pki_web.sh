#!/bin/bash
# vpn_openvpn_pki_web.sh <acao> [args...]
# Um script so pra nao multiplicar arquivos -- cada acao valida seus
# proprios argumentos (mesmo criterio allowlist-no-proprio-script do
# resto do app). Usa o easy-rsa (pacote "easy-rsa") em modo nao
# interativo (EASYRSA_BATCH=1). PKI fica em
# /etc/openvpn/server/easy-rsa/pki -- so root le/escreve.
#
# Acoes:
#   init                        cria CA + certificado do servidor + tls-crypt key
#   emitir_cliente <nome>       emite cert+key de um cliente novo, imprime em JSON
#   baixar_cliente <nome>       reimprime cert+key de um cliente ja emitido (nao precisa reemitir se o admin perdeu o .ovpn)
#   revogar_cliente <nome>      revoga + regera a CRL que o servidor usa

set -u

EASYRSA_BIN="/usr/share/easy-rsa/easyrsa"
PKI_DIR="/etc/openvpn/server/easy-rsa"
ACAO="${1:-}"

if [ ! -x "$EASYRSA_BIN" ]; then
  echo '{"success":false,"message":"easy-rsa não está instalado."}'
  exit 1
fi

nome_valido() {
  [[ "$1" =~ ^[a-zA-Z0-9_-]{1,64}$ ]]
}

json_escapar() {
  # escapa \, ", e troca quebra de linha real por \n literal -- pra
  # conteudo de certificado/chave (com varias linhas) caber numa string
  # JSON de uma linha so.
  sed 's/\\/\\\\/g; s/"/\\"/g' | awk '{printf "%s\\n", $0}'
}

case "$ACAO" in
  init)
    mkdir -p "$PKI_DIR"
    cd "$PKI_DIR" || exit 1

    export EASYRSA_BATCH=1

    if [ ! -d "$PKI_DIR/pki" ]; then
      EASYRSA_REQ_CN="RD-Intranet-VPN-CA" "$EASYRSA_BIN" init-pki >/tmp/rd_pki_out_$$ 2>&1
      EASYRSA_REQ_CN="RD-Intranet-VPN-CA" "$EASYRSA_BIN" build-ca nopass >>/tmp/rd_pki_out_$$ 2>&1
    fi

    if [ ! -f "$PKI_DIR/pki/issued/server.crt" ]; then
      EASYRSA_REQ_CN="server" "$EASYRSA_BIN" build-server-full server nopass >>/tmp/rd_pki_out_$$ 2>&1
    fi

    if [ ! -f "$PKI_DIR/pki/crl.pem" ]; then
      "$EASYRSA_BIN" gen-crl >>/tmp/rd_pki_out_$$ 2>&1
    fi

    if [ ! -f /etc/openvpn/server/tls-crypt.key ]; then
      mkdir -p /etc/openvpn/server
      openvpn --genkey secret /etc/openvpn/server/tls-crypt.key >>/tmp/rd_pki_out_$$ 2>&1
    fi

    if [ ! -f "$PKI_DIR/pki/ca.crt" ] || [ ! -f "$PKI_DIR/pki/issued/server.crt" ]; then
      ERRO="$(tail -20 /tmp/rd_pki_out_$$ | json_escapar)"
      rm -f /tmp/rd_pki_out_$$
      echo "{\"success\":false,\"message\":\"Falha ao inicializar a PKI: ${ERRO}\"}"
      exit 1
    fi
    rm -f /tmp/rd_pki_out_$$

    chmod 700 "$PKI_DIR/pki/private"
    chmod 600 /etc/openvpn/server/tls-crypt.key

    echo '{"success":true,"message":"PKI inicializada com sucesso."}'
    ;;

  emitir_cliente)
    NOME="${2:-}"
    if ! nome_valido "$NOME"; then
      echo '{"success":false,"message":"Nome de cliente inválido."}'
      exit 1
    fi
    if [ -f "$PKI_DIR/pki/issued/${NOME}.crt" ]; then
      echo '{"success":false,"message":"Já existe um certificado emitido para este nome."}'
      exit 1
    fi

    cd "$PKI_DIR" || exit 1
    export EASYRSA_BATCH=1

    if ! EASYRSA_REQ_CN="$NOME" "$EASYRSA_BIN" build-client-full "$NOME" nopass >/tmp/rd_pki_err_$$ 2>&1; then
      ERRO="$(tail -20 /tmp/rd_pki_err_$$ | json_escapar)"
      rm -f /tmp/rd_pki_err_$$
      echo "{\"success\":false,\"message\":\"Falha ao emitir certificado: ${ERRO}\"}"
      exit 1
    fi
    rm -f /tmp/rd_pki_err_$$

    CA="$(json_escapar < "$PKI_DIR/pki/ca.crt")"
    CERT="$(sed -n '/BEGIN CERTIFICATE/,/END CERTIFICATE/p' "$PKI_DIR/pki/issued/${NOME}.crt" | json_escapar)"
    KEY="$(json_escapar < "$PKI_DIR/pki/private/${NOME}.key")"
    TLSCRYPT="$(json_escapar < /etc/openvpn/server/tls-crypt.key)"

    echo "{\"success\":true,\"ca\":\"${CA}\",\"cert\":\"${CERT}\",\"key\":\"${KEY}\",\"tls_crypt\":\"${TLSCRYPT}\"}"
    ;;

  baixar_cliente)
    NOME="${2:-}"
    if ! nome_valido "$NOME"; then
      echo '{"success":false,"message":"Nome de cliente inválido."}'
      exit 1
    fi
    if [ ! -f "$PKI_DIR/pki/issued/${NOME}.crt" ] || [ ! -f "$PKI_DIR/pki/private/${NOME}.key" ]; then
      echo '{"success":false,"message":"Certificado não encontrado (foi revogado ou nunca existiu)."}'
      exit 1
    fi

    CA="$(json_escapar < "$PKI_DIR/pki/ca.crt")"
    CERT="$(sed -n '/BEGIN CERTIFICATE/,/END CERTIFICATE/p' "$PKI_DIR/pki/issued/${NOME}.crt" | json_escapar)"
    KEY="$(json_escapar < "$PKI_DIR/pki/private/${NOME}.key")"
    TLSCRYPT="$(json_escapar < /etc/openvpn/server/tls-crypt.key)"

    echo "{\"success\":true,\"ca\":\"${CA}\",\"cert\":\"${CERT}\",\"key\":\"${KEY}\",\"tls_crypt\":\"${TLSCRYPT}\"}"
    ;;

  revogar_cliente)
    NOME="${2:-}"
    if ! nome_valido "$NOME"; then
      echo '{"success":false,"message":"Nome de cliente inválido."}'
      exit 1
    fi

    cd "$PKI_DIR" || exit 1
    export EASYRSA_BATCH=1

    if ! "$EASYRSA_BIN" revoke "$NOME" >/tmp/rd_pki_err_$$ 2>&1; then
      ERRO="$(tail -20 /tmp/rd_pki_err_$$ | json_escapar)"
      rm -f /tmp/rd_pki_err_$$
      echo "{\"success\":false,\"message\":\"Falha ao revogar: ${ERRO}\"}"
      exit 1
    fi

    if ! "$EASYRSA_BIN" gen-crl >>/tmp/rd_pki_err_$$ 2>&1; then
      ERRO="$(tail -20 /tmp/rd_pki_err_$$ | json_escapar)"
      rm -f /tmp/rd_pki_err_$$
      echo "{\"success\":false,\"message\":\"Certificado revogado, mas falhou ao gerar a CRL: ${ERRO}\"}"
      exit 1
    fi
    rm -f /tmp/rd_pki_err_$$

    # servidor so passa a rejeitar o cliente de fato apos reler a CRL.
    systemctl reload-or-restart openvpn-server@server 2>/dev/null || true

    echo '{"success":true,"message":"Certificado revogado."}'
    ;;

  *)
    echo '{"success":false,"message":"Ação desconhecida."}'
    exit 1
    ;;
esac
