#!/bin/bash
# samba_dc_usuario_listar_web.sh
#
# Lista usuarios do dominio com status habilitado/desabilitado.
# "samba-tool user list" nao mostra status; e preciso um "user show" por
# usuario e ler o bit ACCOUNTDISABLE (0x2) de userAccountControl -- custo
# aceitavel pro volume tipico de uma AD de PME (dezenas/poucas centenas de
# contas). Uma otimizacao futura seria consultar sam.ldb direto via
# ldbsearch numa unica query, fora do escopo desta entrega.
#
# Uma unica chamada de "php -r" no final monta o JSON a partir das linhas
# "usuario|habilitado" coletadas -- evita montar JSON na mao concatenando
# string em bash (fragil e facil de gerar algo invalido).

set -u

LINHAS=()

while IFS= read -r USERNAME; do
  [ -z "$USERNAME" ] && continue

  UAC=$(samba-tool user show "$USERNAME" 2>/dev/null | grep -i '^userAccountControl:' | awk '{print $2}')
  UAC=${UAC:-512}

  HABILITADO="1"
  if (( UAC & 2 )); then
    HABILITADO="0"
  fi

  LINHAS+=("${USERNAME}|${HABILITADO}")
done < <(samba-tool user list 2>/dev/null | sort)

if [ "${#LINHAS[@]}" -eq 0 ]; then
  echo "[]"
  exit 0
fi

php -r '
    $itens = [];
    for ($i = 1; $i < count($argv); $i++) {
        [$username, $habilitado] = explode("|", $argv[$i], 2);
        $itens[] = ["username" => $username, "habilitado" => $habilitado === "1"];
    }
    echo json_encode($itens);
' -- "${LINHAS[@]}"
