<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SambaCompartilhamentoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM samba_compartilhamentos
            ORDER BY nome
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM samba_compartilhamentos
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function contarTotal(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos")
            ->fetchColumn();
    }

    public function contarAtivos(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE status = 'ativo'")
            ->fetchColumn();
    }

    public function contarComLixeira(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE lixeira = 1")
            ->fetchColumn();
    }

    public function contarComBloqueioExtensoes(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE bloqueio_extensoes = 1")
            ->fetchColumn();
    }
}
