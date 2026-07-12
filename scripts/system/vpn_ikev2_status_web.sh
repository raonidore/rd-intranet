#!/bin/bash
# vpn_ikev2_status_web.sh
# So leitura. strongSwan nao tem uma saida machine-friendly no modo
# classico ipsec.conf (isso so existe no swanctl/vici, que este modulo
# nao usa) -- parseia o texto de "ipsec statusall" com awk portavel
# (sem extensoes gawk, pra funcionar em qualquer awk do Ubuntu).
# Melhor esforco: o formato exato pode variar um pouco entre versoes
# do strongSwan, ajustar se necessario depois de testar ao vivo.
#
# Saida:
#   SERVIDOR_ATIVO|0 ou 1
#   CLIENTE|<usuario_eap>|<ip_remoto>|<rx_bytes>|<tx_bytes>

set -u

if ! ipsec status >/dev/null 2>&1; then
  echo "SERVIDOR_ATIVO|0"
  exit 0
fi

echo "SERVIDOR_ATIVO|1"

ipsec statusall 2>/dev/null | awk '
BEGIN { userat=""; ipat="" }
/ESTABLISHED/ {
    line = $0
    idx = index(line, "...")
    if (idx > 0) {
        rest = substr(line, idx+3)
        bidx = index(rest, "[")
        if (bidx > 0) {
            ipat = substr(rest, 1, bidx-1)
            rest2 = substr(rest, bidx+1)
            eidx = index(rest2, "]")
            if (eidx > 0) {
                userat = substr(rest2, 1, eidx-1)
            }
        }
    }
    next
}
/bytes_i/ && userat != "" {
    rx = 0; tx = 0
    n = split($0, campos, ",")
    for (i = 1; i <= n; i++) {
        if (index(campos[i], "bytes_i") > 0) {
            split(campos[i], kv, " ")
            rx = kv[1]
        }
        if (index(campos[i], "bytes_o") > 0) {
            split(campos[i], kv2, " ")
            tx = kv2[1]
        }
    }
    print "CLIENTE|" userat "|" ipat "|" rx "|" tx
    userat = ""
    ipat = ""
}
'
