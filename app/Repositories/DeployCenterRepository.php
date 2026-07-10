<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DeployCenterRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function status(string $modulo): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM configuracao_deploy
            WHERE modulo = ?
            LIMIT 1
        ");

        $stmt->execute([$modulo]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function listarPendentes(): array
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM deploy_pendencias
            ORDER BY criado_em DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pendencias(string $modulo): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM deploy_pendencias
            WHERE modulo = ?
            ORDER BY criado_em DESC
        ");

        $stmt->execute([$modulo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarPendente(string $modulo): void
    {
        // upsert: primeira alteracao pendente de um modulo novo cria a
        // propria linha, nao depende de ela ja existir de antemao.
        $stmt = $this->pdo->prepare("
            INSERT INTO configuracao_deploy (modulo, alteracoes_pendentes)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE alteracoes_pendentes = 1
        ");

        $stmt->execute([$modulo]);
    }

    public function registrarPendencia(
        string $modulo,
        string $tipo,
        ?string $referencia,
        string $descricao
    ): void {
        $usuario = $_SESSION['usuario']['login'] ?? 'sistema';

        $stmt = $this->pdo->prepare("
            INSERT INTO deploy_pendencias
            (modulo, tipo, referencia, descricao, usuario)
            VALUES
            (:modulo, :tipo, :referencia, :descricao, :usuario)
        ");

        $stmt->execute([
            'modulo' => $modulo,
            'tipo' => $tipo,
            'referencia' => $referencia,
            'descricao' => $descricao,
            'usuario' => $usuario,
        ]);
    }

    public function limparPendencias(string $modulo): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM deploy_pendencias
            WHERE modulo = ?
        ");

        $stmt->execute([$modulo]);
    }

    public function marcarAplicado(string $modulo, ?string $backup = null): void
    {
        $usuario = $_SESSION['usuario']['login'] ?? 'sistema';

        $stmt = $this->pdo->prepare("
            INSERT INTO configuracao_deploy
                (modulo, alteracoes_pendentes, ultimo_deploy, ultimo_backup, ultimo_usuario)
            VALUES
                (?, 0, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE
                alteracoes_pendentes = 0,
                ultimo_deploy = NOW(),
                ultimo_backup = VALUES(ultimo_backup),
                ultimo_usuario = VALUES(ultimo_usuario)
        ");

        $stmt->execute([
            $modulo,
            $backup,
            $usuario,
        ]);

        $this->limparPendencias($modulo);
    }
}
