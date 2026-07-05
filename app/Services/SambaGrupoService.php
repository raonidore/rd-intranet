<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class SambaGrupoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Nomes de grupo já em uso (por compartilhamentos ou por usuários),
     * para sugerir nos formulários de criação/edição.
     */
    public function listarNomes(): array
    {
        $stmt = $this->pdo->query("
            SELECT grupo AS nome FROM samba_compartilhamentos
            UNION
            SELECT departamento AS nome FROM samba_usuarios
            ORDER BY nome
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Grupos com detalhe de quem usa cada um (compartilhamentos e usuários),
     * para a tela de consulta de Grupos Samba.
     */
    public function listarComDetalhes(): array
    {
        $grupos = [];

        foreach ($this->listarNomes() as $nome) {
            $grupos[$nome] = [
                'nome' => $nome,
                'compartilhamentos' => [],
                'usuarios' => [],
            ];
        }

        $stmtShares = $this->pdo->query("SELECT nome, grupo FROM samba_compartilhamentos ORDER BY nome");
        foreach ($stmtShares->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grupos[$row['grupo']]['compartilhamentos'][] = $row['nome'];
        }

        $stmtUsers = $this->pdo->query("
            SELECT nome, login, departamento
            FROM samba_usuarios
            WHERE status = 'ativo'
            ORDER BY nome
        ");
        foreach ($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grupos[$row['departamento']]['usuarios'][] = [
                'nome' => $row['nome'],
                'login' => $row['login'],
            ];
        }

        return array_values($grupos);
    }
}
