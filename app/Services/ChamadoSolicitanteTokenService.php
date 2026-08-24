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

        $link = $this->emitirLink((int)$solicitante['id'], $urlBase);
        if ($link === null) {
            return;
        }

        $this->email->enviar(
            $email,
            'Acesso aos seus chamados',
            '<p>Olá, ' . htmlspecialchars($solicitante['nome']) . '!</p>'
            . '<p>Clique no link abaixo pra acompanhar seus chamados:</p>'
            . '<p><a href="' . htmlspecialchars($link) . '">Ver meus chamados</a></p>'
            . '<p>Esse link expira em ' . self::VALIDADE_HORAS . ' hora(s) e só pode ser usado uma vez.</p>'
        );
    }

    /**
     * Gera um token pra um solicitante já conhecido e devolve o link
     * pronto (sem enviar e-mail) -- usado por solicitar() e também por
     * quem precisa convidar o solicitante pra voltar ao portal por outro
     * motivo (ex: ChamadoAvaliacaoService, pedindo avaliação ao resolver
     * o chamado). Invalida qualquer token pendente do solicitante, mesma
     * regra de token único de sempre. Null se SMTP não está configurado
     * (nesse caso não faz sentido gerar um link que ninguém vai receber).
     */
    public function emitirLink(int $solicitanteId, string $urlBase): ?string
    {
        if (!$this->email->configurado()) {
            return null;
        }

        $this->invalidarPendentes($solicitanteId);

        $token = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + self::VALIDADE_HORAS * 3600);

        $stmt = $this->pdo->prepare('INSERT INTO chamados_solicitante_tokens (solicitante_id, token_hash, expira_em) VALUES (?, ?, ?)');
        $stmt->execute([$solicitanteId, hash('sha256', $token), $expiraEm]);

        return rtrim($urlBase, '/') . url('/portal/chamados/acessar?token=' . $token);
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
