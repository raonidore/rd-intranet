<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Área = departamento (TI, Comercial, Financeiro...) -- cadastro
 * simples, igual chamados_externos_categorias. Também é fronteira de
 * permissão: ver projetos_areas_gestores (quem gerencia a área toda
 * sem precisar ser admin do módulo).
 */
class ProjetoAreaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query('SELECT * FROM projetos_areas ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAtivas(): array
    {
        return $this->pdo->query('SELECT * FROM projetos_areas WHERE ativo = 1 ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_areas WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function criar(string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da área.'];
        }
        if ($this->nomeEmUso($nome)) {
            return ['success' => false, 'message' => 'Essa área já existe.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO projetos_areas (nome) VALUES (?)');
        $stmt->execute([$nome]);

        return ['success' => true, 'message' => 'Área cadastrada.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, bool $ativo): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da área.'];
        }
        if ($this->nomeEmUso($nome, $id)) {
            return ['success' => false, 'message' => 'Essa área já existe.'];
        }

        $stmt = $this->pdo->prepare('UPDATE projetos_areas SET nome = ?, ativo = ? WHERE id = ?');
        $stmt->execute([$nome, $ativo ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Área atualizada.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM projetos WHERE area_id = ?');
        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Essa área está em uso por projetos -- desative em vez de excluir.'];
        }

        $this->pdo->prepare('DELETE FROM projetos_areas WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Área removida.'];
    }

    /** @return array<int, array{id:int, nome:string, email:string}> gestores da área, com dados do usuário. */
    public function gestores(int $areaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.id, u.id AS usuario_id, u.nome, u.email
             FROM projetos_areas_gestores g
             JOIN usuarios u ON u.id = g.usuario_id
             WHERE g.area_id = ?
             ORDER BY u.nome'
        );
        $stmt->execute([$areaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string} */
    public function adicionarGestor(int $areaId, int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO projetos_areas_gestores (area_id, usuario_id) VALUES (?, ?)');
        $stmt->execute([$areaId, $usuarioId]);

        return ['success' => true, 'message' => 'Gestor adicionado.'];
    }

    /** @return array{success: bool, message: string} */
    public function removerGestor(int $gestorId): array
    {
        $this->pdo->prepare('DELETE FROM projetos_areas_gestores WHERE id = ?')->execute([$gestorId]);

        return ['success' => true, 'message' => 'Gestor removido.'];
    }

    /** @return int[] ids das áreas onde o usuário é gestor. */
    public function areasGeridasPor(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT area_id FROM projetos_areas_gestores WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function ehGestor(int $areaId, int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM projetos_areas_gestores WHERE area_id = ? AND usuario_id = ?');
        $stmt->execute([$areaId, $usuarioId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM projetos_areas WHERE nome = ?';
        $params = [$nome];

        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}
