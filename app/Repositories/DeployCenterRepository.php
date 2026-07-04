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

    public function marcarPendente(string $modulo): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE configuracao_deploy
               SET alteracoes_pendentes = 1
             WHERE modulo = ?
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
            (
                modulo,
                tipo,
                referencia,
                descricao,
                usuario
            )
            VALUES
            (
                :modulo,
                :tipo,
                :referencia,
                :descricao,
                :usuario
            )
        ");

        $stmt->execute([
            'modulo' => $modulo,
            'tipo' => $tipo,
            'referencia' => $referencia,
            'descricao' => $descricao,
            'usuario' => $usuario,
        ]);
    }

    public function pendencias(string $modulo): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM deploy_pendencias
            WHERE modulo = ?
            ORDER BY id DESC
        ");

        $stmt->execute([$modulo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function limparPendencias(string $modulo): void
    {
        $stmt = $this->pdo->prepare("
            DELETE
            FROM deploy_pendencias
            WHERE modulo = ?
        ");

        $stmt->execute([$modulo]);
    }

    public function marcarAplicado(string $modulo, ?string $backup = null): void
    {
        $usuario = $_SESSION['usuario']['login'] ?? 'sistema';

        $stmt = $this->pdo->prepare("
            UPDATE configuracao_deploy
               SET alteracoes_pendentes = 0,
                   ultimo_deploy = NOW(),
                   ultimo_backup = ?,
                   ultimo_usuario = ?
             WHERE modulo = ?
        ");

        $stmt->execute([
            $backup,
            $usuario,
            $modulo
        ]);

        $this->limparPendencias($modulo);
    }
}
