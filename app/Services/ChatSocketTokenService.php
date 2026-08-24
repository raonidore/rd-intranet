<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Token de curtíssima duração (60s, uso único) pra autenticar a conexão
 * WebSocket do navegador com o chat-bridge -- mesmo padrão hash+expiração
 * do PasswordResetService/ChamadoSolicitanteTokenService, só que
 * pensado pra um handshake que acontece em segundos, não horas: o
 * navegador pede o token autenticado por sessão normal (PHP), manda ele
 * na querystring do WebSocket, e o chat-bridge valida chamando de volta
 * um endpoint interno (protegido por X-Api-Key, não por sessão -- quem
 * chama é o processo Node, não o navegador).
 */
class ChatSocketTokenService
{
    private const VALIDADE_SEGUNDOS = 60;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function emitir(int $usuarioId): string
    {
        // Limpeza oportunista dos tokens vencidos/usados desse usuário --
        // token de 60s não precisa de cron de limpeza, essa tabela nunca
        // acumula lixo relevante.
        $this->pdo->prepare('DELETE FROM chat_socket_tokens WHERE usuario_id = ? AND (usado_em IS NOT NULL OR expira_em < NOW())')
            ->execute([$usuarioId]);

        $token = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + self::VALIDADE_SEGUNDOS);

        $stmt = $this->pdo->prepare('INSERT INTO chat_socket_tokens (usuario_id, token_hash, expira_em) VALUES (?, ?, ?)');
        $stmt->execute([$usuarioId, hash('sha256', $token), $expiraEm]);

        return $token;
    }

    /** Uso único -- valida e já marca como usado na mesma chamada. Retorna o usuario_id, ou null se inválido/expirado/já usado. */
    public function validar(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        $hash = hash('sha256', $token);

        $stmt = $this->pdo->prepare(
            'SELECT id, usuario_id FROM chat_socket_tokens WHERE token_hash = ? AND usado_em IS NULL AND expira_em > NOW() LIMIT 1'
        );
        $stmt->execute([$hash]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            return null;
        }

        $this->pdo->prepare('UPDATE chat_socket_tokens SET usado_em = NOW() WHERE id = ?')->execute([$registro['id']]);

        return (int)$registro['usuario_id'];
    }
}
