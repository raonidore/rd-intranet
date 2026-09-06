<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/** Estatísticas de Projetos -- espelha ChamadoExternoEstatisticaService. */
class ProjetoEstatisticaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function resumo(): array
    {
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM projetos GROUP BY status');
        $porStatus = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'status');

        return [
            'planejamento' => (int)($porStatus['planejamento'] ?? 0),
            'em_andamento' => (int)($porStatus['em_andamento'] ?? 0),
            'pausado' => (int)($porStatus['pausado'] ?? 0),
            'concluido' => (int)($porStatus['concluido'] ?? 0),
            'cancelado' => (int)($porStatus['cancelado'] ?? 0),
            'total' => array_sum($porStatus),
        ];
    }

    /** @return array<int, array{area_nome:string, total:int}> */
    public function porArea(): array
    {
        return $this->pdo->query(
            'SELECT a.nome AS area_nome, COUNT(p.id) AS total
             FROM projetos_areas a
             LEFT JOIN projetos p ON p.area_id = a.id
             GROUP BY a.id, a.nome
             ORDER BY total DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{coluna:string, total:int}> tarefas em aberto, por coluna do kanban -- pro gráfico de rosca. */
    public function tarefasPorColuna(): array
    {
        return $this->pdo->query(
            "SELECT coluna, COUNT(*) AS total FROM projetos_tarefas GROUP BY coluna"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{mes:string, total:int}> últimos $meses meses, incluindo os com zero projeto aberto. */
    public function porMes(int $meses = 6): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(aberto_em, '%Y-%m') AS mes, COUNT(*) AS total
             FROM projetos
             WHERE aberto_em >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY mes
             ORDER BY mes"
        );
        $stmt->bindValue(1, $meses, PDO::PARAM_INT);
        $stmt->execute();

        $porMes = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'mes');

        $resultado = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $chave = date('Y-m', strtotime("-{$i} months"));
            $resultado[] = ['mes' => $chave, 'total' => (int)($porMes[$chave] ?? 0)];
        }

        return $resultado;
    }
}
