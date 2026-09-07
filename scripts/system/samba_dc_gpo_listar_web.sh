#!/bin/bash
# samba_dc_gpo_listar_web.sh
#
# Lista as GPOs do dominio. "samba-tool gpo listall" imprime um bloco
# "campo       : valor" por GPO, separado por linha em branco -- extrai só
# guid/nome/versao (os campos estaveis e documentados do formato).

set -u

SAIDA=$(samba-tool gpo listall 2>/dev/null)

GUID=""
NOME=""
VERSAO=""
LINHAS=()

fechar_bloco() {
  if [ -n "$GUID" ]; then
    LINHAS+=("${GUID}|${NOME}|${VERSAO}")
  fi
  GUID=""
  NOME=""
  VERSAO=""
}

while IFS= read -r LINHA; do
  if [ -z "$LINHA" ]; then
    fechar_bloco
    continue
  fi
  CAMPO=$(echo "$LINHA" | cut -d: -f1 | sed 's/ *$//')
  VALOR=$(echo "$LINHA" | cut -d: -f2- | sed 's/^ *//')
  case "$CAMPO" in
    GPO) GUID="$VALOR" ;;
    "display name") NOME="$VALOR" ;;
    version) VERSAO="$VALOR" ;;
  esac
done <<< "$SAIDA"
fechar_bloco

if [ "${#LINHAS[@]}" -eq 0 ]; then
  echo "[]"
  exit 0
fi

php -r '
    $itens = [];
    for ($i = 1; $i < count($argv); $i++) {
        [$guid, $nome, $versao] = explode("|", $argv[$i], 3);
        $itens[] = ["guid" => $guid, "nome" => $nome, "versao" => $versao];
    }
    echo json_encode($itens);
' -- "${LINHAS[@]}"
