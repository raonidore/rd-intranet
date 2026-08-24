<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ChamadoSlaService
{
    public const PRIORIDADES = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'urgente' => 'Urgente'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** As 4 linhas (uma por prioridade) de uma categoria -- sempre existem desde a criação da categoria (ChamadoCategoriaService::criar()). */
    public function listarPorCategoria(int $categoriaId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM chamados_slas WHERE categoria_id = ? ORDER BY FIELD(prioridade, 'urgente','alta','media','baixa')");
        $stmt->execute([$categoriaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $categoriaId, string $prioridade): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_slas WHERE categoria_id = ? AND prioridade = ?');
        $stmt->execute([$categoriaId, $prioridade]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, int $tempoPrimeiraRespostaMin, int $tempoResolucaoMin): array
    {
        if ($tempoPrimeiraRespostaMin < 1 || $tempoResolucaoMin < 1) {
            return ['success' => false, 'message' => 'Os prazos precisam ser maiores que zero.'];
        }

        if ($tempoPrimeiraRespostaMin > $tempoResolucaoMin) {
            return ['success' => false, 'message' => 'O prazo de primeira resposta não pode ser maior que o de resolução.'];
        }

        $stmt = $this->pdo->prepare('UPDATE chamados_slas SET tempo_primeira_resposta_min = ?, tempo_resolucao_min = ? WHERE id = ?');
        $stmt->execute([$tempoPrimeiraRespostaMin, $tempoResolucaoMin, $id]);

        return ['success' => true, 'message' => 'SLA atualizado.'];
    }
}
