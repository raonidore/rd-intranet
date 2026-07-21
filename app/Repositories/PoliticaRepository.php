<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PoliticaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @return array<string, array> regra_id => linha de estado, só as regras que já têm alguma linha salva pra esse ativo. */
    public function estadoPorAtivo(int $ativoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_politicas_estado WHERE ativo_id = ?');
        $stmt->execute([$ativoId]);

        $porRegra = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $porRegra[$linha['regra_id']] = $linha;
        }

        return $porRegra;
    }

    public function upsertEstado(int $ativoId, string $regraId, int $desejado, string $status, ?string $mensagem, ?int $solicitacaoId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ativos_politicas_estado (ativo_id, regra_id, desejado, status, mensagem, solicitacao_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                desejado = VALUES(desejado),
                status = VALUES(status),
                mensagem = VALUES(mensagem),
                solicitacao_id = VALUES(solicitacao_id)
        ');

        $stmt->execute([$ativoId, $regraId, $desejado, $status, $mensagem, $solicitacaoId]);
    }

    /** @return array<int, array> linhas de ativos_politicas_estado ainda 'pendente' presas a essa solicitação. */
    public function porSolicitacao(int $solicitacaoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ativos_politicas_estado WHERE solicitacao_id = ?');
        $stmt->execute([$solicitacaoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function atualizarStatus(int $id, string $status, ?string $mensagem): void
    {
        $stmt = $this->pdo->prepare('UPDATE ativos_politicas_estado SET status = ?, mensagem = ? WHERE id = ?');
        $stmt->execute([$status, $mensagem, $id]);
    }
}
