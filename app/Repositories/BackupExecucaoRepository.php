<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class BackupExecucaoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function criar(int $destinoId, string $tipo): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO backup_execucoes (destino_id, tipo, status)
            VALUES (?, ?, 'executando')
        ");
        $stmt->execute([$destinoId, $tipo]);

        return (int)$this->pdo->lastInsertId();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM backup_execucoes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function finalizar(
        int $id,
        string $status,
        int $arquivosEnviados,
        int $bytesEnviados,
        int $versoesCriadas,
        ?string $mensagemErro
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE backup_execucoes
            SET status = ?, arquivos_enviados = ?, bytes_enviados = ?, versoes_criadas = ?,
                mensagem_erro = ?, finalizado_em = NOW()
            WHERE id = ? AND status = 'executando'
        ");
        $stmt->execute([$status, $arquivosEnviados, $bytesEnviados, $versoesCriadas, $mensagemErro, $id]);
    }

    /**
     * Execução ainda em andamento (se houver) -- usado pra retomar o
     * acompanhamento ao vivo quando o admin sai da tela e volta (ou nunca
     * clicou em "Rodar agora" nesta sessão de navegador, ex: backup
     * disparado pelo cron agendado).
     */
    public function buscarEmAndamento(): ?array
    {
        $stmt = $this->pdo->query("
            SELECT e.*, d.nome AS destino_nome
            FROM backup_execucoes e
            LEFT JOIN backup_destinos d ON d.id = e.destino_id
            WHERE e.status = 'executando'
            ORDER BY e.id DESC
            LIMIT 1
        ");

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function listar(int $limite = 30): array
    {
        $limite = max(1, min($limite, 200));

        $stmt = $this->pdo->query("
            SELECT e.*, d.nome AS destino_nome, d.provider AS destino_provider
            FROM backup_execucoes e
            LEFT JOIN backup_destinos d ON d.id = e.destino_id
            ORDER BY e.id DESC
            LIMIT {$limite}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarPorDestino(int $destinoId, int $limite = 10): array
    {
        $limite = max(1, min($limite, 100));

        $stmt = $this->pdo->prepare("
            SELECT * FROM backup_execucoes
            WHERE destino_id = ?
            ORDER BY id DESC
            LIMIT {$limite}
        ");
        $stmt->execute([$destinoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
