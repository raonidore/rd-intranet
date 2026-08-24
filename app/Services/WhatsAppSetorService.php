<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

class WhatsAppSetorService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query(
            "SELECT s.*, COUNT(su.usuario_id) AS total_usuarios
             FROM whatsapp_setores s
             LEFT JOIN whatsapp_setor_usuarios su ON su.setor_id = s.id
             GROUP BY s.id
             ORDER BY s.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_setores WHERE id = ?');
        $stmt->execute([$id]);

        $setor = $stmt->fetch(PDO::FETCH_ASSOC);

        return $setor ?: null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function criar(string $nome): array
    {
        $nome = trim($nome);

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do setor.'];
        }

        if ($this->nomeEmUso($nome)) {
            return ['success' => false, 'message' => 'Já existe um setor com esse nome.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO whatsapp_setores (nome) VALUES (?)');
        $stmt->execute([$nome]);

        return ['success' => true, 'message' => 'Setor criado com sucesso.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function atualizar(int $id, string $nome, bool $ativo, bool $npsAtivo = false, bool $visivelEquipe = false): array
    {
        $nome = trim($nome);

        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do setor.'];
        }

        if ($this->nomeEmUso($nome, $id)) {
            return ['success' => false, 'message' => 'Já existe um setor com esse nome.'];
        }

        $stmt = $this->pdo->prepare('UPDATE whatsapp_setores SET nome = ?, ativo = ?, nps_ativo = ?, visivel_equipe = ? WHERE id = ?');
        $stmt->execute([$nome, $ativo ? 1 : 0, $npsAtivo ? 1 : 0, $visivelEquipe ? 1 : 0, $id]);

        return ['success' => true, 'message' => 'Setor atualizado com sucesso.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('DELETE FROM whatsapp_setores WHERE id = ?');
        $stmt->execute([$id]);

        return ['success' => true, 'message' => 'Setor removido.'];
    }

    /**
     * IDs dos setores em que o usuário atende -- usado pra filtrar a
     * Fila (admin vê todos os setores, o resto só os seus).
     *
     * @return int[]
     */
    public function idsSetoresDoUsuario(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT setor_id FROM whatsapp_setor_usuarios WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * IDs dos usuários já vinculados ao setor -- usado pra marcar os
     * checkboxes já selecionados na tela.
     *
     * @return int[]
     */
    public function idsUsuariosDoSetor(int $setorId): array
    {
        $stmt = $this->pdo->prepare('SELECT usuario_id FROM whatsapp_setor_usuarios WHERE setor_id = ?');
        $stmt->execute([$setorId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * IDs dos usuários do setor marcados como supervisor -- usado pra
     * marcar os checkboxes já selecionados na tela.
     *
     * @return int[]
     */
    public function idsSupervisoresDoSetor(int $setorId): array
    {
        $stmt = $this->pdo->prepare('SELECT usuario_id FROM whatsapp_setor_usuarios WHERE setor_id = ? AND supervisor = 1');
        $stmt->execute([$setorId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function ehMembroDoSetor(int $usuarioId, int $setorId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM whatsapp_setor_usuarios WHERE setor_id = ? AND usuario_id = ?');
        $stmt->execute([$setorId, $usuarioId]);

        return (bool)$stmt->fetchColumn();
    }

    public function ehSupervisorDoSetor(int $usuarioId, int $setorId): bool
    {
        $stmt = $this->pdo->prepare('SELECT supervisor FROM whatsapp_setor_usuarios WHERE setor_id = ? AND usuario_id = ?');
        $stmt->execute([$setorId, $usuarioId]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Usuários vinculados ao setor, com nome -- base pra montar a lista
     * de "transferir direto pro colega" (o chamador filtra quem está
     * online e tira o próprio usuário da lista).
     *
     * @return array<int, array{id: int, nome: string}>
     */
    public function usuariosDoSetor(int $setorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.nome
             FROM whatsapp_setor_usuarios su
             JOIN usuarios u ON u.id = su.usuario_id
             WHERE su.setor_id = ? AND u.ativo = 1
             ORDER BY u.nome'
        );
        $stmt->execute([$setorId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Substitui a lista de usuários do setor pelo array informado, com
     * quem é supervisor (delete + insert numa transação -- lista
     * costuma ter poucos usuários, não vale a pena calcular diff
     * incremental). $supervisorIds fora de $usuarioIds é ignorado --
     * não faz sentido marcar supervisor quem não é nem membro.
     *
     * @return array{success: bool, message: string}
     */
    public function salvarUsuariosDoSetor(int $setorId, array $usuarioIds, array $supervisorIds = []): array
    {
        $usuarioIds = array_values(array_unique(array_map('intval', $usuarioIds)));
        $supervisorIds = array_values(array_intersect(array_map('intval', $supervisorIds), $usuarioIds));

        $this->pdo->beginTransaction();

        try {
            $del = $this->pdo->prepare('DELETE FROM whatsapp_setor_usuarios WHERE setor_id = ?');
            $del->execute([$setorId]);

            if ($usuarioIds) {
                $ins = $this->pdo->prepare('INSERT INTO whatsapp_setor_usuarios (setor_id, usuario_id, supervisor) VALUES (?, ?, ?)');
                foreach ($usuarioIds as $usuarioId) {
                    $ins->execute([$setorId, $usuarioId, in_array($usuarioId, $supervisorIds, true) ? 1 : 0]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao salvar usuários do setor.'];
        }

        return ['success' => true, 'message' => 'Usuários do setor atualizados.'];
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM whatsapp_setores WHERE nome = ?';
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
