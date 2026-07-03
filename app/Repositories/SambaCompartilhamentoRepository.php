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

    /**
     * Lista todos os compartilhamentos.
     */
    public function listar(): array
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM samba_compartilhamentos
            ORDER BY nome
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um compartilhamento pelo ID.
     */
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

    /**
     * Busca um compartilhamento pelo nome.
     */
    public function buscarPorNome(string $nome): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM samba_compartilhamentos
            WHERE nome = ?
            LIMIT 1
        ");

        $stmt->execute([$nome]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /**
     * Cria um novo compartilhamento.
     */
    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO samba_compartilhamentos
            (
                nome,
                descricao,
                caminho,
                grupo,
                somente_leitura,
                lixeira,
                bloqueio_extensoes,
                status
            )
            VALUES
            (
                :nome,
                :descricao,
                :caminho,
                :grupo,
                :somente_leitura,
                :lixeira,
                :bloqueio_extensoes,
                'ativo'
            )
        ");

        $stmt->execute([
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'],
            'caminho' => $dados['caminho'],
            'grupo' => $dados['grupo'],
            'somente_leitura' => $dados['somente_leitura'],
            'lixeira' => $dados['lixeira'],
            'bloqueio_extensoes' => $dados['bloqueio_extensoes'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Total de compartilhamentos.
     */
    public function contarTotal(): int
    {
        return (int) $this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos")
            ->fetchColumn();
    }

    /**
     * Compartilhamentos ativos.
     */
    public function contarAtivos(): int
    {
        return (int) $this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE status='ativo'")
            ->fetchColumn();
    }

    /**
     * Compartilhamentos com lixeira.
     */
    public function contarComLixeira(): int
    {
        return (int) $this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE lixeira=1")
            ->fetchColumn();
    }

    /**
     * Compartilhamentos com bloqueio de extensões.
     */
    public function contarComBloqueioExtensoes(): int
    {
        return (int) $this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE bloqueio_extensoes=1")
            ->fetchColumn();
    }
}
