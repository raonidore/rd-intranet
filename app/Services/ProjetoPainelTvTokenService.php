<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Link de exibição do Modo TV -- variação de LONGA DURAÇÃO do link
 * mágico do participante externo: sem expiração automática nem
 * "usado_em" de uso único, pensado pra colar numa TV e esquecer. Só
 * sai do ar se alguém revogar manualmente.
 */
class ProjetoPainelTvTokenService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function gerar(int $areaId, ?int $criadoPor): string
    {
        $token = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare('INSERT INTO projetos_paineis_tv (area_id, token_hash, criado_por) VALUES (?, ?, ?)');
        $stmt->execute([$areaId, hash('sha256', $token), $criadoPor]);

        return $token;
    }

    /** @return array{id: int, area_id: int}|null */
    public function validarToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, area_id FROM projetos_paineis_tv WHERE token_hash = ? AND revogado_em IS NULL LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array<int, array{id:int, area_id:int, area_nome:string, criado_em:string}> links ativos, pra tela de gerenciar. */
    public function listarPorArea(int $areaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.area_id, a.nome AS area_nome, p.criado_em
             FROM projetos_paineis_tv p
             JOIN projetos_areas a ON a.id = p.area_id
             WHERE p.area_id = ? AND p.revogado_em IS NULL
             ORDER BY p.criado_em DESC'
        );
        $stmt->execute([$areaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revogar(int $id): void
    {
        $this->pdo->prepare('UPDATE projetos_paineis_tv SET revogado_em = NOW() WHERE id = ?')->execute([$id]);
    }
}
