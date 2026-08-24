<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * "Online agora" sem sessão persistida em banco nenhuma -- usa
 * usuarios.ultimo_acesso, tocado a cada request autenticado
 * (AuthMiddleware::check()). Como o sistema já faz polling de badge a
 * cada poucos segundos em qualquer tela com a aba aberta, isso já
 * funciona como um heartbeat de fato sem precisar de endpoint dedicado.
 * "Online" = teve algum request nos últimos MINUTOS_ONLINE minutos.
 */
class UsuarioOnlineService
{
    private const MINUTOS_ONLINE = 3;
    private const THROTTLE_SEGUNDOS = 60;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Chamado em todo request autenticado -- só escreve de fato se
     * fizer mais de THROTTLE_SEGUNDOS desde o último touch, pra não
     * bater no banco a cada requisição (poller roda a cada poucos
     * segundos enquanto a aba está aberta).
     */
    public function registrarAcesso(int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?
             AND (ultimo_acesso IS NULL OR ultimo_acesso <= NOW() - INTERVAL ? SECOND)'
        );
        $stmt->execute([$usuarioId, self::THROTTLE_SEGUNDOS]);
    }

    public function estaOnline(int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM usuarios WHERE id = ? AND ultimo_acesso >= NOW() - INTERVAL ? MINUTE'
        );
        $stmt->execute([$usuarioId, self::MINUTOS_ONLINE]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param int[] $usuarioIds
     * @return int[] subconjunto que está online agora
     */
    public function idsOnline(array $usuarioIds): array
    {
        $usuarioIds = array_values(array_unique(array_map('intval', $usuarioIds)));
        if (empty($usuarioIds)) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($usuarioIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id FROM usuarios WHERE id IN ({$marcadores}) AND ultimo_acesso >= NOW() - INTERVAL ? MINUTE"
        );
        $stmt->execute([...$usuarioIds, self::MINUTOS_ONLINE]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
