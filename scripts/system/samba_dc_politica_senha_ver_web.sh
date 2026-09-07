#!/bin/bash
# samba_dc_politica_senha_ver_web.sh
#
# Le a politica de senha atual do dominio ("samba-tool domain
# passwordsettings show"), formato "Rotulo: valor" por linha.

set -u

SAIDA=$(samba-tool domain passwordsettings show 2>&1)
if [ $? -ne 0 ]; then
  php -r 'echo json_encode(["success" => false, "message" => "Falha ao consultar política de senha: " . $argv[1]]);' -- "$(printf '%s' "$SAIDA" | tail -5)"
  exit 1
fi

extrair() {
  printf '%s' "$SAIDA" | grep -i "^$1:" | head -1 | cut -d: -f2- | sed 's/^ *//;s/ *$//'
}

COMPLEXIDADE=$(extrair "Password complexity")
HISTORICO=$(extrair "Password history length")
TAM_MIN=$(extrair "Minimum password length")
IDADE_MIN=$(extrair "Minimum password age")
IDADE_MAX=$(extrair "Maximum password age")
BLOQ_DURACAO=$(extrair "Account lockout duration")
BLOQ_LIMITE=$(extrair "Account lockout threshold")
BLOQ_RESET=$(extrair "Reset account lockout")

php -r '
    echo json_encode([
        "success" => true,
        "complexidade" => strtolower(trim($argv[1])) === "on",
        "historico" => (int)$argv[2],
        "tamanho_minimo" => (int)$argv[3],
        "idade_minima_dias" => (int)$argv[4],
        "idade_maxima_dias" => (int)$argv[5],
        "bloqueio_duracao_min" => (int)$argv[6],
        "bloqueio_limite_tentativas" => (int)$argv[7],
        "bloqueio_reset_min" => (int)$argv[8],
    ]);
' -- "$COMPLEXIDADE" "$HISTORICO" "$TAM_MIN" "$IDADE_MIN" "$IDADE_MAX" "$BLOQ_DURACAO" "$BLOQ_LIMITE" "$BLOQ_RESET"
