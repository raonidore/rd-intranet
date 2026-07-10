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
     * Roda as migrations pendentes em ordem. Para no primeiro erro.
     *
     * @return array{success: bool, aplicadas: string[], erro: ?string}
     */
    public function aplicar(): array
    {
        $aplicadas = [];

        foreach ($this->pendentes() as $arquivo) {
            $sql = file_get_contents(self::DIRETORIO . '/' . $arquivo);

            try {
                foreach ($this->comandos($sql) as $comando) {
                    $this->pdo->exec($comando);
                }

                $stmt = $this->pdo->prepare("INSERT INTO migrations_aplicadas (arquivo) VALUES (?)");
                $stmt->execute([$arquivo]);

                $aplicadas[] = $arquivo;
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'aplicadas' => $aplicadas,
                    'erro' => "Falha em {$arquivo}: " . $e->getMessage(),
                ];
            }
        }

        return ['success' => true, 'aplicadas' => $aplicadas, 'erro' => null];
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
