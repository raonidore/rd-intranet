<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Quem abre o chamado -- pessoa da empresa cliente, sem login. Mesmo
 * papel de WhatsAppContatoService::buscarOuCriarPorNumero(), só que
 * casando por e-mail (preferência) ou telefone em vez de número único.
 */
class ChamadoSolicitanteService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function buscarOuCriar(string $nome, ?string $email, ?string $telefone, ?int $unidadeId): array
    {
        $nome = trim($nome);
        $email = $email !== null ? strtolower(trim($email)) : null;
        $telefone = $telefone !== null ? preg_replace('/\D+/', '', $telefone) : null;
        $email = $email ?: null;
        $telefone = $telefone ?: null;

        $existente = null;
        if ($email !== null) {
            $existente = $this->buscarPorEmail($email);
        }
        if ($existente === null && $telefone !== null) {
            $existente = $this->buscarPorTelefone($telefone);
        }

        if ($existente) {
            $stmt = $this->pdo->prepare(
                'UPDATE chamados_solicitantes SET nome = ?, email = COALESCE(?, email), telefone = COALESCE(?, telefone), unidade_id = COALESCE(?, unidade_id) WHERE id = ?'
            );
            $stmt->execute([$nome, $email, $telefone, $unidadeId, $existente['id']]);

            return $this->buscarPorId((int)$existente['id']);
        }

        $ins = $this->pdo->prepare('INSERT INTO chamados_solicitantes (nome, email, telefone, unidade_id) VALUES (?, ?, ?, ?)');
        $ins->execute([$nome, $email, $telefone, $unidadeId]);

        return $this->buscarPorId((int)$this->pdo->lastInsertId());
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_solicitantes WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_solicitantes WHERE email = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function buscarPorTelefone(string $telefone): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chamados_solicitantes WHERE telefone = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([preg_replace('/\D+/', '', $telefone)]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }
}
