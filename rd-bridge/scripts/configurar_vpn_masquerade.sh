#!/bin/bash
# configurar_vpn_masquerade.sh <interface_lan> [interface_wireguard]
#
# Roda UMA VEZ no coletor RD.Bridge (Raspberry Pi / VM Linux), depois que
# o WireGuard já está configurado e o túnel com a nuvem está no ar --
# NÃO faz nada de WireGuard em si (chaves, peer, etc.), só o
# encaminhamento de pacote que faz o modo VPN funcionar sem exigir que o
# cliente crie rota nenhuma no roteador dele.
#
# Por que masquerade em vez de rota "de verdade": o equipamento local
# (NVR, gateway, switch) nunca precisa saber que existe uma VPN -- o
# coletor troca o IP de origem do pacote pelo dele próprio antes de
# repassar pra rede local, então a resposta volta pro coletor
# normalmente, como se fosse ele quem tivesse perguntado. Isso só
# funciona porque o padrão de acesso é sempre "nuvem pergunta,
# equipamento responde" -- nunca o equipamento iniciando contato sozinho
# com a nuvem (se precisasse disso, seria necessário rota simétrica de
# verdade, não masquerade).
#
# Idempotente: rodar de novo não duplica regra (checa antes de inserir).

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Precisa rodar como root (sudo)." >&2
  exit 1
fi

IFACE_LAN="${1:-}"
IFACE_WG="${2:-wg0}"

if [ -z "$IFACE_LAN" ]; then
  echo "Uso: $0 <interface_lan> [interface_wireguard=wg0]" >&2
  echo "Exemplo: $0 eth0 wg0" >&2
  exit 1
fi

if ! ip link show "$IFACE_LAN" >/dev/null 2>&1; then
  echo "Interface '$IFACE_LAN' não existe nesta máquina." >&2
  exit 1
fi

# 1) IP forwarding -- sem isso o kernel descarta pacote que não é
#    destinado a ele mesmo, mesmo com as regras de iptables certas.
if [ "$(cat /proc/sys/net/ipv4/ip_forward)" != "1" ]; then
  sysctl -w net.ipv4.ip_forward=1 >/dev/null
fi

# Persiste o forwarding entre reboots -- sem isso, some depois de
# reiniciar o coletor e o modo VPN para de funcionar silenciosamente.
cat > /etc/sysctl.d/99-rdbridge-forward.conf <<EOF
net.ipv4.ip_forward = 1
EOF

# 2) Masquerade -- troca o IP de origem do pacote (vindo do túnel WireGuard,
#    destinado à rede local) pelo IP do próprio coletor na interface LAN,
#    antes de sair. O equipamento local responde pro coletor, não pra
#    nuvem diretamente -- nenhuma rota nova precisa existir em lugar
#    nenhum além desta máquina.
if ! iptables -t nat -C POSTROUTING -o "$IFACE_LAN" -j MASQUERADE 2>/dev/null; then
  iptables -t nat -A POSTROUTING -o "$IFACE_LAN" -j MASQUERADE
fi

# 3) Encaminhamento entre o túnel e a LAN, nos dois sentidos -- sem isso
#    o kernel bloqueia o pacote mesmo com forwarding ligado e masquerade
#    configurado (política padrão de FORWARD costuma ser DROP).
if ! iptables -C FORWARD -i "$IFACE_WG" -o "$IFACE_LAN" -j ACCEPT 2>/dev/null; then
  iptables -A FORWARD -i "$IFACE_WG" -o "$IFACE_LAN" -j ACCEPT
fi
if ! iptables -C FORWARD -i "$IFACE_LAN" -o "$IFACE_WG" -m state --state RELATED,ESTABLISHED -j ACCEPT 2>/dev/null; then
  iptables -A FORWARD -i "$IFACE_LAN" -o "$IFACE_WG" -m state --state RELATED,ESTABLISHED -j ACCEPT
fi

# Persiste as regras de iptables entre reboots, se o pacote estiver
# disponível (Debian/Ubuntu/Raspberry Pi OS -- netfilter-persistent é o
# padrão nessas distros; se não tiver instalado, avisa em vez de falhar
# calado, já que sem isso as regras somem no próximo boot).
if command -v netfilter-persistent >/dev/null 2>&1; then
  netfilter-persistent save >/dev/null 2>&1 || true
else
  echo "Aviso: 'netfilter-persistent' não encontrado -- as regras de iptables não sobrevivem a um reboot." >&2
  echo "Instale com: apt-get install iptables-persistent" >&2
fi

echo "OK: masquerade configurado (${IFACE_WG} -> ${IFACE_LAN})."
