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

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO samba_compartilhamentos
            (nome, descricao, caminho, grupo, somente_leitura, lixeira, bloqueio_extensoes, status)
            VALUES
            (:nome, :descricao, :caminho, :grupo, :somente_leitura, :lixeira, :bloqueio_extensoes, 'ativo')
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

        return (int)$this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE samba_compartilhamentos
               SET nome = :nome,
                   descricao = :descricao,
                   grupo = :grupo,
                   caminho = :caminho
             WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'],
            'grupo' => $dados['grupo'],
            'caminho' => $dados['caminho'],
        ]);
    }

    public function atualizarSeguranca(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE samba_compartilhamentos
               SET somente_leitura = :somente_leitura,
                   lixeira = :lixeira,
                   bloqueio_extensoes = :bloqueio_extensoes
             WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'somente_leitura' => $dados['somente_leitura'],
            'lixeira' => $dados['lixeira'],
            'bloqueio_extensoes' => $dados['bloqueio_extensoes'],
        ]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM samba_compartilhamentos
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    public function usuariosAutorizados(int $compartilhamentoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT usuario_id, leitura, escrita, exclusao
            FROM samba_compartilhamento_usuarios
            WHERE compartilhamento_id = ?
        ");

        $stmt->execute([$compartilhamentoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarUsuariosAutorizados(int $compartilhamentoId, array $usuarios): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM samba_compartilhamento_usuarios
            WHERE compartilhamento_id = ?
        ");

        $stmt->execute([$compartilhamentoId]);

        $insert = $this->pdo->prepare("
            INSERT INTO samba_compartilhamento_usuarios
            (compartilhamento_id, usuario_id, leitura, escrita, exclusao)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($usuarios as $usuario) {
            $insert->execute([
                $compartilhamentoId,
                $usuario['usuario_id'],
                $usuario['leitura'],
                $usuario['escrita'],
                $usuario['exclusao'],
            ]);
        }
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
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE status='ativo'")
            ->fetchColumn();
    }

    public function contarComLixeira(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE lixeira=1")
            ->fetchColumn();
    }

    public function contarComBloqueioExtensoes(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM samba_compartilhamentos WHERE bloqueio_extensoes=1")
            ->fetchColumn();
    }
}
