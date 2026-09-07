#!/bin/bash
# seguranca_auditoria_local_web.sh
#
# Auditoria OFFLINE das contas locais deste proprio servidor -- nao toca
# rede nenhuma, so le algo que o processo (root, via sudo) ja tem acesso
# total de qualquer forma (/etc/shadow). "unshadow" junta passwd+shadow
# no formato que o john entende, "john" tenta crackear cada hash contra
# a wordlist curada do proprio projeto. Sincrono: wordlist pequena
# (poucas centenas de linhas) contra poucas contas locais e rapido.

set -u

WORDLIST="/var/www/rd.intranet/resources/wordlists/senhas_comuns.txt"

command -v john >/dev/null 2>&1 || { echo '{"success":false,"message":"john não está instalado. Instale em Infraestrutura > Dependências."}'; exit 1; }
command -v unshadow >/dev/null 2>&1 || { echo '{"success":false,"message":"unshadow não encontrado (deveria vir junto do pacote john)."}'; exit 1; }
[ -f "$WORDLIST" ] || { echo '{"success":false,"message":"Wordlist não encontrada no servidor."}'; exit 1; }

UNSHADOW_TMP="/tmp/rd_unshadow_$$"
JOHN_POT="/tmp/rd_john_pot_$$"

if ! unshadow /etc/passwd /etc/shadow > "$UNSHADOW_TMP" 2>/dev/null; then
  rm -f "$UNSHADOW_TMP"
  echo '{"success":false,"message":"Falha ao ler contas locais."}'
  exit 1
fi

john --wordlist="$WORDLIST" --pot="$JOHN_POT" "$UNSHADOW_TMP" >/dev/null 2>&1

SAIDA=$(john --show --pot="$JOHN_POT" "$UNSHADOW_TMP" 2>/dev/null)
rm -f "$UNSHADOW_TMP" "$JOHN_POT" "${JOHN_POT}.bak" 2>/dev/null

# Formato de "--show": uma linha "usuario:senha:..." por conta crackeada,
# seguida de um resumo "N password hash cracked, M left". Filtra só as
# linhas de conta (tem pelo menos dois ":") e ignora o resumo.
LINHAS=()
while IFS= read -r LINHA; do
  [ -z "$LINHA" ] && continue
  case "$LINHA" in
    *password\ hash*|*"0 password hashes cracked"*) continue ;;
  esac
  [[ "$LINHA" == *:*:* ]] || continue
  USUARIO="${LINHA%%:*}"
  RESTO="${LINHA#*:}"
  SENHA="${RESTO%%:*}"
  [ -z "$USUARIO" ] && continue
  LINHAS+=("${USUARIO}|${SENHA}")
done <<< "$SAIDA"

if [ "${#LINHAS[@]}" -eq 0 ]; then
  echo '{"success":true,"contas_fracas":[]}'
  exit 0
fi

php -r '
    $itens = [];
    for ($i = 1; $i < count($argv); $i++) {
        [$usuario, $senha] = array_pad(explode("|", $argv[$i], 2), 2, "");
        $itens[] = ["usuario" => $usuario, "senha" => $senha];
    }
    echo json_encode(["success" => true, "contas_fracas" => $itens]);
' -- "${LINHAS[@]}"
