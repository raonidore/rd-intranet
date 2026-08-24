<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Login por link mágico do Portal do Solicitante -- mesmo padrão de
 * hash+expiração do PasswordResetService, só que pra chamados_solicitantes
 * em vez de usuarios (visitante externo nunca tem conta no sistema).
 * Por design, solicitar() nunca revela se o e-mail tem chamado ou não
 * (mesma lógica anti-enumeração do PasswordResetService).
 */
class ChamadoSolicitanteTokenService
{
    private const VALIDADE_HORAS = 2;

    private PDO $pdo;
    private EmailService $email;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->email = new EmailService();
    }

    public function solicitar(string $email, string $urlBase): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || !$this->email->configurado()) {
            return;
        }

        $solicitante = (new ChamadoSolicitanteService())->buscarPorEmail($email);
        if (!$solicitante) {
            return;
        }

        $this->invalidarPendentes((int)$solicitante['id']);

        $token = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + self::VALIDADE_HORAS * 3600);

        $stmt = $this->pdo->prepare('INSERT INTO chamados_solicitante_tokens (solicitante_id, token_hash, expira_em) VALUES (?, ?, ?)');
        $stmt->execute([$solicitante['id'], hash('sha256', $token), $expiraEm]);

        $link = rtrim($urlBase, '/') . url('/portal/chamados/acessar?token=' . $token);

        $this->email->enviar(
            $email,
            'Acesso aos seus chamados',
            '<p>Olá, ' . htmlspecialchars($solicitante['nome']) . '!</p>'
            . '<p>Clique no link abaixo pra acompanhar seus chamados:</p>'
            . '<p><a href="' . htmlspecialchars($link) . '">Ver meus chamados</a></p>'
            . '<p>Esse link expira em ' . self::VALIDADE_HORAS . ' hora(s) e só pode ser usado uma vez.</p>'
        );
    }

    /** @return array{id: int, solicitante_id: int}|null */
    public function validarToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, solicitante_id FROM chamados_solicitante_tokens
             WHERE token_hash = ? AND usado_em IS NULL AND expira_em > NOW()
             LIMIT 1"
        );
        $stmt->execute([hash('sha256', $token)]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function marcarUsado(int $tokenId): void
    {
        $this->pdo->prepare('UPDATE chamados_solicitante_tokens SET usado_em = NOW() WHERE id = ?')->execute([$tokenId]);
    }

    private function invalidarPendentes(int $solicitanteId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE chamados_solicitante_tokens SET usado_em = NOW() WHERE solicitante_id = ? AND usado_em IS NULL'
        );
        $stmt->execute([$solicitanteId]);
    }
}
