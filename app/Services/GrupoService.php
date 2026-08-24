<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Grupos de usuários -- peça genérica, não específica de nenhum
 * módulo. Serve pra duas coisas: (1) conceder módulos existentes em
 * bloco pro grupo inteiro (grupo_modulos, resolvido na hora do login
 * junto com usuario_modulos -- ver AuthController::login()), e (2) ser
 * "dono" de permissão em módulos futuros que precisem de granularidade
 * mais fina que módulo inteiro (ex: Documentos, por categoria).
 */
class GrupoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query(
            "SELECT g.*, COUNT(gu.usuario_id) AS total_usuarios
             FROM grupos g
             LEFT JOIN grupo_usuarios gu ON gu.grupo_id = g.id
             GROUP BY g.id
             ORDER BY g.nome"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM grupos WHERE id = ?');
        $stmt->execute([$id]);

        $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

        return $grupo ?: null;
    }

    /** @return array{success: bool, message: string} */
    public function criar(string $nome, string $descricao): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do grupo.'];
        }
        if ($this->nomeEmUso($nome)) {
            return ['success' => false, 'message' => 'Já existe um grupo com esse nome.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO grupos (nome, descricao) VALUES (?, ?)');
        $stmt->execute([$nome, trim($descricao) ?: null]);

        return ['success' => true, 'message' => 'Grupo criado com sucesso.'];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, string $nome, string $descricao): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome do grupo.'];
        }
        if ($this->nomeEmUso($nome, $id)) {
            return ['success' => false, 'message' => 'Já existe um grupo com esse nome.'];
        }

        $stmt = $this->pdo->prepare('UPDATE grupos SET nome = ?, descricao = ? WHERE id = ?');
        $stmt->execute([$nome, trim($descricao) ?: null, $id]);

        return ['success' => true, 'message' => 'Grupo atualizado com sucesso.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $this->pdo->prepare('DELETE FROM grupos WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Grupo removido.'];
    }

    /** @return int[] */
    public function idsUsuariosDoGrupo(int $grupoId): array
    {
        $stmt = $this->pdo->prepare('SELECT usuario_id FROM grupo_usuarios WHERE grupo_id = ?');
        $stmt->execute([$grupoId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return int[] */
    public function idsGruposDoUsuario(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT grupo_id FROM grupo_usuarios WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int, array{id:int, nome:string}> */
    public function nomesDosGrupos(array $grupoIds): array
    {
        if (empty($grupoIds)) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($grupoIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, nome FROM grupos WHERE id IN ({$marcadores}) ORDER BY nome");
        $stmt->execute(array_values(array_map('intval', $grupoIds)));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string} */
    public function salvarUsuariosDoGrupo(int $grupoId, array $usuarioIds): array
    {
        $usuarioIds = array_values(array_unique(array_map('intval', $usuarioIds)));

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare('DELETE FROM grupo_usuarios WHERE grupo_id = ?')->execute([$grupoId]);

            if ($usuarioIds) {
                $ins = $this->pdo->prepare('INSERT INTO grupo_usuarios (grupo_id, usuario_id) VALUES (?, ?)');
                foreach ($usuarioIds as $usuarioId) {
                    $ins->execute([$grupoId, $usuarioId]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao salvar usuários do grupo.'];
        }

        return ['success' => true, 'message' => 'Usuários do grupo atualizados.'];
    }

    /**
     * Substitui os grupos de UM usuário (direção inversa de
     * salvarUsuariosDoGrupo) -- usado na tela de edição de usuário.
     *
     * @return array{success: bool, message: string}
     */
    public function salvarGruposDoUsuario(int $usuarioId, array $grupoIds): array
    {
        $grupoIds = array_values(array_unique(array_map('intval', $grupoIds)));

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare('DELETE FROM grupo_usuarios WHERE usuario_id = ?')->execute([$usuarioId]);

            if ($grupoIds) {
                $ins = $this->pdo->prepare('INSERT INTO grupo_usuarios (grupo_id, usuario_id) VALUES (?, ?)');
                foreach ($grupoIds as $grupoId) {
                    $ins->execute([$grupoId, $usuarioId]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao salvar grupos do usuário.'];
        }

        return ['success' => true, 'message' => 'Grupos do usuário atualizados.'];
    }

    /** @return string[] chaves de módulo (ModuloCatalogo) */
    public function modulosDoGrupo(int $grupoId): array
    {
        $stmt = $this->pdo->prepare('SELECT modulo FROM grupo_modulos WHERE grupo_id = ?');
        $stmt->execute([$grupoId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array{success: bool, message: string} */
    public function salvarModulosDoGrupo(int $grupoId, array $modulos): array
    {
        $modulos = array_values(array_intersect($modulos, ModuloCatalogo::chaves()));

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare('DELETE FROM grupo_modulos WHERE grupo_id = ?')->execute([$grupoId]);

            if ($modulos) {
                $ins = $this->pdo->prepare('INSERT INTO grupo_modulos (grupo_id, modulo) VALUES (?, ?)');
                foreach ($modulos as $modulo) {
                    $ins->execute([$grupoId, $modulo]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao salvar módulos do grupo.'];
        }

        return ['success' => true, 'message' => 'Módulos do grupo atualizados.'];
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM grupos WHERE nome = ?';
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
