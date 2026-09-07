#!/bin/bash
# ip_scanner_wol_web.sh <mac>
#
# Envia um magic packet de Wake-on-LAN (UDP broadcast, porta 9) pro MAC
# informado. Usa python3 (ja obrigatorio no catalogo, mesmo binario que
# lote_arquivos_samba_web.sh ja usa) em vez de fsockopen do PHP -- um
# broadcast UDP de verdade exige a opcao de socket SO_BROADCAST, que o
# fsockopen/stream_socket_client do PHP nao expoe sem a extensao
# "sockets" (cuja presenca nao da pra garantir); o modulo socket do
# python3 ja cobre isso de forma padrao, sem dependencia nova nenhuma.

set -u

MAC="$1"
MAC_LIMPO=$(echo "$MAC" | tr -d ':.-' | tr '[:lower:]' '[:upper:]')

if [[ ! "$MAC_LIMPO" =~ ^[0-9A-F]{12}$ ]]; then
  echo '{"success":false,"message":"Endereço MAC inválido."}'
  exit 1
fi

python3 -c '
import socket, sys, binascii, json

mac = sys.argv[1]
mac_bytes = binascii.unhexlify(mac)
pacote = b"\xff" * 6 + mac_bytes * 16

sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
sock.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
try:
    sock.sendto(pacote, ("255.255.255.255", 9))
    print(json.dumps({"success": True, "message": "Pacote Wake-on-LAN enviado."}))
except Exception as e:
    print(json.dumps({"success": False, "message": "Falha ao enviar: " + str(e)}))
finally:
    sock.close()
' "$MAC_LIMPO"
