#!/bin/bash
# meshcentral_configurar_rede_web.sh <lan|rede>
#
# Alterna o MeshCentral entre dois modos de alcance:
#
#   lan  -- so agentes na MESMA rede local do servidor (sem ponto no
#           CommonName do certificado => o MeshCentral entra sozinho em
#           "LAN mode": os agentes recebem MeshServer=local e dependem de
#           descoberta por broadcast/multicast, que nao atravessa VLAN
#           roteada).
#   rede -- qualquer maquina que tenha ROTA ate o servidor, mesmo em outra
#           VLAN (CommonName = IP do servidor, que tem ponto => os agentes
#           recebem MeshServer=wss://<ip>:<porta>/... fixo, sem depender de
#           broadcast).
#
# Depois de trocar e reiniciar o servico, regenera os instaladores estaticos
# ja hospedados em storage/uploads/mesh/*.exe, reaproveitando o mesmo MeshID
# que cada um ja tinha embutido (extraido do proprio .exe antigo) -- so a
# politica de rede muda, o vinculo com o grupo/dispositivo continua o mesmo.

set -u

PASTA_DADOS="/opt/meshcentral/meshcentral-data"
CONFIG="$PASTA_DADOS/config.json"
PORTA=4430
PASTA_MESH="/var/www/rd.intranet/storage/uploads/mesh"
USUARIO_WEB="www-data"

MODO="${1:-}"
if [ "$MODO" != "lan" ] && [ "$MODO" != "rede" ]; then
  echo '{"success":false,"message":"Modo invalido. Use \"lan\" ou \"rede\"."}'
  exit 1
fi

if [ ! -f "$CONFIG" ]; then
  echo '{"success":false,"message":"MeshCentral nao esta instalado (config.json nao encontrado)."}'
  exit 1
fi

if [ "$MODO" = "rede" ]; then
  CERT_ALVO="$(ip -4 addr show scope global | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -1)"
  if [ -z "$CERT_ALVO" ]; then
    echo '{"success":false,"message":"Nao foi possivel detectar o IP do servidor na rede local."}'
    exit 1
  fi
else
  CERT_ALVO="meshcentral"
fi

node -e "
const fs = require('fs');
const caminho = '$CONFIG';
const config = JSON.parse(fs.readFileSync(caminho, 'utf8'));
if (!config.settings) config.settings = {};
config.settings.cert = '$CERT_ALVO';
fs.writeFileSync(caminho, JSON.stringify(config, null, 2));
"

systemctl restart meshcentral

OK=0
for i in $(seq 1 15); do
  sleep 1
  if systemctl is-active --quiet meshcentral; then OK=1; break; fi
done

if [ "$OK" -ne 1 ]; then
  ULTIMO_LOG="$(journalctl -u meshcentral -n 20 --no-pager | tr '\n' ' ' | sed 's/"/\\"/g')"
  echo "{\"success\":false,\"message\":\"MeshCentral nao voltou a rodar apos a troca de modo. Log: ${ULTIMO_LOG}\"}"
  exit 1
fi

# Espera o servidor HTTPS realmente aceitar conexao antes de tentar baixar
# os instaladores. Quando o CommonName do certificado muda (troca de modo),
# o MeshCentral regenera na hora os certificados (HTTPS, assinatura de
# codigo, AMT) e reassina os proprios executaveis do agente -- leva mais que
# alguns segundos, e systemd ja reporta o servico "active" bem antes disso
# terminar (o processo Node subiu, so ainda nao esta pronto).
HTTPS_PRONTO=0
for i in $(seq 1 60); do
  CODE="$(curl -k -s -o /dev/null -w '%{http_code}' "https://127.0.0.1:${PORTA}/meshagents?id=4" 2>/dev/null)"
  if [ "$CODE" != "000" ]; then HTTPS_PRONTO=1; break; fi
  sleep 1
done

if [ "$HTTPS_PRONTO" -ne 1 ]; then
  echo "{\"success\":true,\"message\":\"Modo de rede trocado, mas o servidor HTTPS nao respondeu a tempo pra regenerar os instaladores automaticamente. Tente novamente em um minuto ou baixe manualmente pelo console do MeshCentral.\",\"modo\":\"$MODO\",\"cert\":\"$CERT_ALVO\"}"
  exit 0
fi

declare -A ARCH_ID=( ["x86"]=3 ["x64"]=4 ["arm64"]=43 )
REGERADOS=""
FALHAS=""

for ARQ in x86 x64 arm64; do
  ARQUIVO="$PASTA_MESH/$ARQ.exe"
  [ -f "$ARQUIVO" ] || continue

  MESHID_HEX="$(strings "$ARQUIVO" | grep -oP '(?<=MeshID=0x)[0-9A-Fa-f]+' | head -1)"
  if [ -z "$MESHID_HEX" ]; then
    FALHAS="$FALHAS $ARQ(sem-meshid-encontrado-no-arquivo-atual)"
    continue
  fi

  MESHID_PARAM="$(node -e "
    const hex = '$MESHID_HEX';
    let b64 = Buffer.from(hex, 'hex').toString('base64');
    b64 = b64.replace(/\+/g, '@').replace(/\//g, '\$').replace(/=+\$/, '');
    process.stdout.write(b64);
  ")"

  TMP="$(mktemp)"
  HTTP_CODE="$(curl -k -s -o "$TMP" -w '%{http_code}' "https://127.0.0.1:${PORTA}/meshagents?id=${ARCH_ID[$ARQ]}&meshid=${MESHID_PARAM}")"

  if [ "$HTTP_CODE" = "200" ] && [ -s "$TMP" ]; then
    mv "$TMP" "$ARQUIVO"
    chown "$USUARIO_WEB":"$USUARIO_WEB" "$ARQUIVO"
    chmod 644 "$ARQUIVO"
    REGERADOS="$REGERADOS $ARQ"
  else
    rm -f "$TMP"
    FALHAS="$FALHAS $ARQ(http-$HTTP_CODE)"
  fi
done

if [ "$MODO" = "rede" ]; then
  DESC_MODO="toda a rede (agentes conectam em wss://${CERT_ALVO}:${PORTA}, alcança qualquer VLAN roteada até aqui)"
else
  DESC_MODO="somente rede local (agentes dependem de broadcast/multicast, não atravessam VLAN roteada)"
fi

MSG="MeshCentral reconfigurado para ${DESC_MODO}."
if [ -n "$REGERADOS" ]; then MSG="$MSG Instaladores regenerados:$REGERADOS."; fi
if [ -n "$FALHAS" ]; then MSG="$MSG Falha ao regenerar automaticamente:$FALHAS -- baixe manualmente pelo console do MeshCentral e reenvie na tela de instaladores."; fi
MSG="$MSG Máquinas que já tinham o MeshAgent instalado precisam reinstalar com o instalador atualizado pra passar a valer."

echo "{\"success\":true,\"message\":\"${MSG}\",\"modo\":\"$MODO\",\"cert\":\"$CERT_ALVO\"}"
