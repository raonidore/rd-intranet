<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AntivirusRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function criarVerificacao(string $tipo, string $caminho): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO antivirus_verificacoes (tipo, caminho, status)
            VALUES (?, ?, 'executando')
        ");
        $stmt->execute([$tipo, $caminho]);

        return (int)$this->pdo->lastInsertId();
    }

    public function finalizarVerificacao(
        int $id,
        string $status,
        int $arquivosVerificados,
        int $ameacasEncontradas,
        string $saida
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE antivirus_verificacoes
            SET status = ?, arquivos_verificados = ?, ameacas_encontradas = ?, saida = ?, finalizado_em = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $arquivosVerificados, $ameacasEncontradas, $saida, $id]);
    }

    public function registrarAmeaca(int $verificacaoId, string $caminhoOriginal, ?string $caminhoQuarentena, string $assinatura): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO antivirus_ameacas (verificacao_id, caminho_original, caminho_quarentena, assinatura, acao)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $verificacaoId,
            $caminhoOriginal,
            $caminhoQuarentena,
            $assinatura,
            $caminhoQuarentena ? 'quarentena' : 'ignorado',
        ]);
    }

    public function listarVerificacoes(int $limite = 20): array
    {
        $limite = max(1, min($limite, 100));

        $stmt = $this->pdo->query("SELECT * FROM antivirus_verificacoes ORDER BY id DESC LIMIT {$limite}");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarQuarentena(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM antivirus_ameacas
             WHERE acao = 'quarentena'
             ORDER BY id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarAmeaca(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM antivirus_ameacas WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function atualizarAcaoAmeaca(int $id, string $acao): void
    {
        $stmt = $this->pdo->prepare("UPDATE antivirus_ameacas SET acao = ? WHERE id = ?");
        $stmt->execute([$acao, $id]);
    }
}
