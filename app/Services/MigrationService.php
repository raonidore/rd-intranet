<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class MigrationService
{
    private const DIRETORIO = __DIR__ . '/../../database/migrations';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->garantirTabela();
    }

    /**
     * @return string[] nomes dos arquivos .sql ainda nao aplicados, em ordem
     */
    public function pendentes(): array
    {
        $aplicadas = $this->pdo->query("SELECT arquivo FROM migrations_aplicadas")
            ->fetchAll(PDO::FETCH_COLUMN);

        $todas = array_map('basename', glob(self::DIRETORIO . '/*.sql'));
        sort($todas);

        return array_values(array_diff($todas, $aplicadas));
    }

    /**
     * Erros do MySQL/MariaDB que significam "a mudança que essa
     * migration faria já existe no banco" -- coluna/tabela/índice/FK já
     * presentes. Acontece quando o banco chegou nesse estado por outro
     * caminho (schema.sql de uma instalação anterior já incluía a
     * coluna, restauração de backup, intervenção manual) sem que
     * migrations_aplicadas tivesse o registro correspondente. Visto ao
     * vivo num cliente: 2026_07_27_ativos_rede_credencial.sql falhou com
     * "Duplicate column name 'rede_usuario'" -- a coluna já existia (o
     * schema.sql de quando o servidor foi instalado já vinha com ela),
     * só a linha em migrations_aplicadas é que nunca tinha sido gravada.
     * Sem esse tratamento, TODA migration seguinte no arquivo (inclusive
     * as de verdade novas, de atualizações futuras) ficava bloqueada pra
     * sempre, já que aplicar() para no primeiro erro.
     */
    private const CODIGOS_MYSQL_JA_EXISTE = [
        1050, // ER_TABLE_EXISTS_ERROR
        1060, // ER_DUP_FIELDNAME (coluna)
        1061, // ER_DUP_KEYNAME (índice)
        1826, // ER_DUP_CONSTRAINT_NAME (FK)
    ];

    /**
     * Roda as migrations pendentes em ordem. Para no primeiro erro que
     * não seja "já existe" (ver CODIGOS_MYSQL_JA_EXISTE) -- esses são
     * marcados como aplicados sem reexecutar, não travam o resto.
     *
     * @return array{success: bool, aplicadas: string[], puladas: string[], erro: ?string}
     */
    public function aplicar(): array
    {
        $aplicadas = [];
        $puladas = [];

        foreach ($this->pendentes() as $arquivo) {
            $sql = file_get_contents(self::DIRETORIO . '/' . $arquivo);

            try {
                foreach ($this->comandos($sql) as $comando) {
                    $this->pdo->exec($comando);
                }

                $this->marcarAplicada($arquivo);
                $aplicadas[] = $arquivo;
            } catch (\Throwable $e) {
                if ($this->erroIndicaJaExiste($e)) {
                    $this->marcarAplicada($arquivo);
                    $puladas[] = $arquivo;
                    continue;
                }

                return [
                    'success' => false,
                    'aplicadas' => $aplicadas,
                    'puladas' => $puladas,
                    'erro' => "Falha em {$arquivo}: " . $e->getMessage(),
                ];
            }
        }

        return ['success' => true, 'aplicadas' => $aplicadas, 'puladas' => $puladas, 'erro' => null];
    }

    private function erroIndicaJaExiste(\Throwable $e): bool
    {
        if (!$e instanceof \PDOException) {
            return false;
        }

        $codigoMysql = (int)($e->errorInfo[1] ?? 0);

        return in_array($codigoMysql, self::CODIGOS_MYSQL_JA_EXISTE, true);
    }

    private function marcarAplicada(string $arquivo): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO migrations_aplicadas (arquivo) VALUES (?)");
        $stmt->execute([$arquivo]);
    }

    /**
     * Erros esperados ao reaplicar INSERTs antigos sobre o schema final:
     * linha já existe, ou o INSERT referencia coluna/tabela que uma
     * migration posterior removeu/renomeou (ex: o INSERT ... SELECT de
     * ativos_contadores usa a coluna antiga ativos.tipo).
     */
    private const CODIGOS_MYSQL_DADO_INICIAL_IGNORAVEL = [
        1062, // ER_DUP_ENTRY
        1054, // ER_BAD_FIELD_ERROR (coluna não existe mais)
        1146, // ER_NO_SUCH_TABLE
    ];

    /**
     * Reaplica só os INSERTs (dados iniciais: tipos de ativo, categorias/
     * setores/SLAs de chamados, configurações padrão, unidade padrão...)
     * de TODAS as migrations. Usado pelo install.sh logo depois de carregar
     * o schema.sql e marcar as migrations como aplicadas -- o schema.sql é
     * só estrutura, então sem isto um servidor novo nasce sem esses dados
     * (visto no enzilabnto, 12/10/2026: Ativos sem nenhum tipo além de
     * Periféricos). Idempotente o bastante pra rodar de novo: duplicado é
     * ignorado.
     *
     * @return array{inseridas: int, ignoradas: int, erros: string[]}
     */
    public function aplicarDadosIniciais(): array
    {
        $inseridas = 0;
        $ignoradas = 0;
        $erros = [];

        $arquivos = glob(self::DIRETORIO . '/*.sql');
        sort($arquivos);

        foreach ($arquivos as $arquivo) {
            foreach ($this->comandos(file_get_contents($arquivo)) as $comando) {
                if (!preg_match('/^INSERT\b/i', $comando)) {
                    continue;
                }

                try {
                    $inseridas += (int)$this->pdo->exec($comando);
                } catch (\PDOException $e) {
                    $codigo = (int)($e->errorInfo[1] ?? 0);

                    if (in_array($codigo, self::CODIGOS_MYSQL_DADO_INICIAL_IGNORAVEL, true)
                        || in_array($codigo, self::CODIGOS_MYSQL_JA_EXISTE, true)) {
                        $ignoradas++;
                        continue;
                    }

                    $erros[] = basename($arquivo) . ': ' . $e->getMessage();
                }
            }
        }

        return ['inseridas' => $inseridas, 'ignoradas' => $ignoradas, 'erros' => $erros];
    }

    /**
     * Divide o arquivo .sql em comandos individuais, ignorando linhas de
     * comentario (--). Suficiente para as migrations deste projeto, que nao
     * usam stored procedures/triggers com ';' interno.
     *
     * @return string[]
     */
    private function comandos(string $sql): array
    {
        $semComentarios = preg_replace('/^--.*$/m', '', $sql);

        return array_values(array_filter(array_map('trim', explode(';', $semComentarios))));
    }

    private function garantirTabela(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations_aplicadas (
                arquivo VARCHAR(180) NOT NULL PRIMARY KEY,
                aplicado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
