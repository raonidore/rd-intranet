#!/bin/bash
# gerar_schema.sh
#
# Regenera database/schema.sql a partir do banco de producao atual --
# rode isso sempre que adicionar migrations novas, senao um servidor
# instalado do zero (scripts/install.sh) fica sem as tabelas novas: o
# install.sh marca TODAS as migrations como "ja aplicadas" na hora de
# carregar o schema.sql (presume que ele ja reflete o estado final),
# entao uma tabela que so existe via migration e nunca chega a ser
# criada de verdade num servidor novo se o schema.sql ficar desatualizado.
#
# So a ESTRUTURA (schema.sql e so `mysqldump --no-data`) -- dados
# semeados por migrations (INSERT IGNORE de config padrao, etc.) nao
# entram aqui, mas isso e coberto de outra forma: ConfigService::get()
# sempre tem um valor padrao no proprio codigo PHP, e o usuario admin
# padrao (criado pelo install.sh) tem perfil "admin", que ja bypassa
# checagem de modulo (usuario_modulos) por completo.
#
# Uso: bash scripts/gerar_schema.sh

set -euo pipefail

DIR_REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_CONFIG="$DIR_REPO/app/Config/database.php"
SAIDA="$DIR_REPO/database/schema.sql"

if [ ! -f "$DB_CONFIG" ]; then
  echo "Nao achei $DB_CONFIG -- rode isso na raiz do checkout, com o banco ja configurado." >&2
  exit 1
fi

DB_NOME=$(php -r "echo parse_url((require '$DB_CONFIG')['dsn'])['host'] ? preg_replace('/.*dbname=([^;]+).*/', '\$1', (require '$DB_CONFIG')['dsn']) : '';" 2>/dev/null || true)
DB_USUARIO=$(php -r "echo (require '$DB_CONFIG')['user'];")
DB_SENHA=$(php -r "echo (require '$DB_CONFIG')['password'];")

if [ -z "$DB_NOME" ]; then
  # fallback: extrai dbname direto da DSN sem depender do parse_url acima
  DB_NOME=$(php -r "preg_match('/dbname=([^;]+)/', (require '$DB_CONFIG')['dsn'], \$m); echo \$m[1];")
fi

echo "Gerando schema a partir do banco '$DB_NOME'..."

DUMP_BRUTO="$(mktemp)"
trap 'rm -f "$DUMP_BRUTO"' EXIT

mysqldump -u "$DB_USUARIO" -p"$DB_SENHA" \
  --no-data --skip-comments --compact --skip-set-charset --skip-add-locks --skip-disable-keys \
  "$DB_NOME" > "$DUMP_BRUTO"

php -r '
$bruto = file_get_contents($argv[1]);

// remove comentarios condicionais de versao do mysqldump (/*M!...*/, /*!40101...*/)
$bruto = preg_replace("/^\/\*[M!].*\*\/;?\s*$/m", "", $bruto);
$bruto = preg_replace("/\n{2,}/", "\n", $bruto);

preg_match_all("/CREATE TABLE `(\w+)` \(.*?\n\) ENGINE=[^;]+;/s", $bruto, $m, PREG_SET_ORDER);

$tabelas = [];
foreach ($m as $bloco) {
    $nome = $bloco[1];
    $sql = preg_replace("/^CREATE TABLE `/", "CREATE TABLE IF NOT EXISTS `", $bloco[0]);
    $sql = preg_replace("/ AUTO_INCREMENT=\d+/", "", $sql);
    $tabelas[$nome] = $sql;
}

ksort($tabelas);

$saida = "-- Schema base da RD Intranet, gerado a partir do banco de producao.\n";
$saida .= "-- Usado apenas na instalacao de um servidor novo (scripts/install.sh):\n";
$saida .= "-- cria todas as tabelas ja no estado final, sem precisar repetir o\n";
$saida .= "-- historico incremental de database/migrations/ (algumas dessas\n";
$saida .= "-- migrations usam ALTER TABLE, que nao e seguro reaplicar aqui).\n";
$saida .= "-- Gerado em " . date("Y-m-d H:i:s") . ".\n\n";
$saida .= "-- Import nao respeita ordem de dependencia entre tabelas (algumas tem FK\n";
$saida .= "-- pra tabelas que so aparecem depois neste arquivo) -- desliga a checagem\n";
$saida .= "-- soh durante o import, como o proprio mysqldump faz.\n";
$saida .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tabelas as $nome => $sql) {
    $saida .= "-- ----------------------------------------------------------------\n";
    $saida .= "-- {$nome}\n";
    $saida .= "-- ----------------------------------------------------------------\n";
    $saida .= $sql . "\n\n";
}

$saida .= "SET FOREIGN_KEY_CHECKS=1;\n";

file_put_contents($argv[2], $saida);
echo "OK: " . count($tabelas) . " tabelas escritas em {$argv[2]}\n";
' "$DUMP_BRUTO" "$SAIDA"

echo "Pronto. Revise o diff (git diff database/schema.sql) antes de commitar."
