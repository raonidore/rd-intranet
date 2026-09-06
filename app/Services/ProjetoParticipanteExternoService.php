<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Pessoa de fora do sistema atribuída a uma tarefa (consultor
 * terceirizado, ponto focal do cliente, técnico do fornecedor) --
 * mesmo raciocínio de ChamadoSolicitanteService::buscarOuCriar(), só
 * casando por e-mail (preferência) ou telefone, sem exigir virar
 * fornecedor formal só pra entrar num projeto.
 */
class ProjetoParticipanteExternoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function buscarOuCriar(string $nome, ?string $email, ?string $telefone, ?string $empresa): array
    {
        $nome = trim($nome);
        $email = $email !== null ? strtolower(trim($email)) : null;
        $telefone = $telefone !== null ? preg_replace('/\D+/', '', $telefone) : null;
        $email = $email ?: null;
        $telefone = $telefone ?: null;
        $empresa = $empresa !== null ? (trim($empresa) ?: null) : null;

        $existente = null;
        if ($email !== null) {
            $existente = $this->buscarPorEmail($email);
        }
        if ($existente === null && $telefone !== null) {
            $existente = $this->buscarPorTelefone($telefone);
        }

        if ($existente) {
            $stmt = $this->pdo->prepare(
                'UPDATE projetos_participantes_externos SET nome = ?, email = COALESCE(?, email), telefone = COALESCE(?, telefone), empresa = COALESCE(?, empresa) WHERE id = ?'
            );
            $stmt->execute([$nome, $email, $telefone, $empresa, $existente['id']]);

            return $this->buscarPorId((int)$existente['id']);
        }

        $ins = $this->pdo->prepare('INSERT INTO projetos_participantes_externos (nome, email, telefone, empresa) VALUES (?, ?, ?, ?)');
        $ins->execute([$nome, $email, $telefone, $empresa]);

        return $this->buscarPorId((int)$this->pdo->lastInsertId());
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_participantes_externos WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_participantes_externos WHERE email = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorTelefone(string $telefone): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projetos_participantes_externos WHERE telefone = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([preg_replace('/\D+/', '', $telefone)]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /** @return array<int, array> pra autocomplete -- nome, e-mail ou empresa contendo o termo. */
    public function buscarPorTermo(string $termo, int $limite = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM projetos_participantes_externos
             WHERE nome LIKE ? OR email LIKE ? OR empresa LIKE ?
             ORDER BY nome LIMIT ?'
        );
        $like = '%' . $termo . '%';
        $stmt->bindValue(1, $like);
        $stmt->bindValue(2, $like);
        $stmt->bindValue(3, $like);
        $stmt->bindValue(4, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
