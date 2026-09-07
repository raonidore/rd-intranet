#!/bin/bash
# seguranca_credenciais_padrao_web.sh <ip> <servico>
#
# Testa uma lista curta e curada de credenciais de fabrica conhecidas
# (admin/admin, root/root, etc.) contra UM host e UM servico -- nao e
# forca bruta de verdade (sem combinatoria usuario x senha, "-C" usa
# pares fixos ja definidos), so confere se o dispositivo ainda esta com
# a senha de fabrica. Sincrono, com timeout curto -- "-f" para no
# primeiro par que funcionar.

set -u

IP="$1"
SERVICO="$2"

REGEX_IPV4='^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$'
if [[ ! "$IP" =~ $REGEX_IPV4 ]]; then
  echo '{"success":false,"message":"IP inválido."}'
  exit 1
fi

OCTETO1="${BASH_REMATCH[1]}"
OCTETO2="${BASH_REMATCH[2]}"

PRIVADO=0
if [ "$OCTETO1" -eq 10 ]; then
  PRIVADO=1
elif [ "$OCTETO1" -eq 172 ] && [ "$OCTETO2" -ge 16 ] && [ "$OCTETO2" -le 31 ]; then
  PRIVADO=1
elif [ "$OCTETO1" -eq 192 ] && [ "$OCTETO2" -eq 168 ]; then
  PRIVADO=1
elif [ "$OCTETO1" -eq 169 ] && [ "$OCTETO2" -eq 254 ]; then
  PRIVADO=1
fi

if [ "$PRIVADO" -ne 1 ]; then
  echo '{"success":false,"message":"Só é permitido testar dispositivos em faixa de rede privada (RFC1918) ou link-local."}'
  exit 1
fi

if [ "$SERVICO" != "ssh" ] && [ "$SERVICO" != "ftp" ] && [ "$SERVICO" != "telnet" ]; then
  echo '{"success":false,"message":"Serviço não suportado (use ssh, ftp ou telnet)."}'
  exit 1
fi

command -v hydra >/dev/null 2>&1 || { echo '{"success":false,"message":"hydra não está instalado. Instale em Infraestrutura > Dependências."}'; exit 1; }

WORDLIST="/var/www/rd.intranet/resources/wordlists/credenciais_padrao.txt"
[ -f "$WORDLIST" ] || { echo '{"success":false,"message":"Lista de credenciais padrão não encontrada no servidor."}'; exit 1; }

SAIDA=$(timeout 60 hydra -C "$WORDLIST" -t 4 -f "$IP" "$SERVICO" 2>&1)

# Linha de sucesso do hydra: "[porta][servico] host: <ip>   login: <usuario>   password: <senha>"
if echo "$SAIDA" | grep -q 'login:'; then
  LINHA_ACHADA=$(echo "$SAIDA" | grep 'login:' | head -1)
  USUARIO=$(echo "$LINHA_ACHADA" | sed -n 's/.*login: *\([^ ]*\).*/\1/p')
  SENHA=$(echo "$LINHA_ACHADA" | sed -n 's/.*password: *\([^ ]*\).*/\1/p')
  php -r 'echo json_encode(["success" => true, "encontrado" => true, "usuario" => $argv[1], "senha" => $argv[2]]);' -- "$USUARIO" "$SENHA"
else
  echo '{"success":true,"encontrado":false}'
fi
