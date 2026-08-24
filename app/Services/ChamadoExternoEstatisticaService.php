<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/** "Quantas vezes esse problema aconteceu" -- contagens simples pra apontar fornecedor/equipamento problemático. */
class ChamadoExternoEstatisticaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function resumo(): array
    {
        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) AS total FROM chamados_externos GROUP BY status"
        );
        $porStatus = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'status');

        return [
            'aberto' => (int)($porStatus['aberto'] ?? 0),
            'aguardando_fornecedor' => (int)($porStatus['aguardando_fornecedor'] ?? 0),
            'em_andamento' => (int)($porStatus['em_andamento'] ?? 0),
            'resolvido' => (int)($porStatus['resolvido'] ?? 0),
            'fechado' => (int)($porStatus['fechado'] ?? 0),
            'total' => array_sum($porStatus),
        ];
    }

    /** @return array<int, array{fornecedor_id:int, fornecedor_nome:string, total:int}> */
    public function porFornecedor(int $limite = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT f.id AS fornecedor_id, f.nome_fantasia AS fornecedor_nome, COUNT(ce.id) AS total
             FROM chamados_externos ce
             JOIN fornecedores f ON f.id = ce.fornecedor_id
             GROUP BY f.id, f.nome_fantasia
             ORDER BY total DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{categoria_nome:string, total:int}> */
    public function porCategoria(): array
    {
        return $this->pdo->query(
            "SELECT COALESCE(cat.nome, 'Sem categoria') AS categoria_nome, COUNT(ce.id) AS total
             FROM chamados_externos ce
             LEFT JOIN chamados_externos_categorias cat ON cat.id = ce.categoria_id
             GROUP BY categoria_nome
             ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array{mes:string, total:int}> últimos $meses meses, incluindo os com zero chamados. */
    public function porMes(int $meses = 6): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(aberto_em, '%Y-%m') AS mes, COUNT(*) AS total
             FROM chamados_externos
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

    /** @return array<int, array> histórico de chamados externos ligados a um ativo específico -- "quantas vezes esse equipamento deu problema". */
    public function porAtivo(int $ativoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ce.*, f.nome_fantasia AS fornecedor_nome
             FROM chamados_externos ce
             JOIN fornecedores f ON f.id = ce.fornecedor_id
             WHERE ce.ativo_id = ?
             ORDER BY ce.aberto_em DESC"
        );
        $stmt->execute([$ativoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
