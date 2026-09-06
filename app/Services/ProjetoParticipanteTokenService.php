<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Login por link mágico do participante externo de Projetos -- cópia
 * quase literal de ChamadoSolicitanteTokenService (hash+expiração,
 * uso único, nunca revela se o e-mail tem participação ou não).
 */
class ProjetoParticipanteTokenService
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

        $participante = (new ProjetoParticipanteExternoService())->buscarPorEmail($email);
        if (!$participante) {
            return;
        }

        $link = $this->emitirLink((int)$participante['id'], $urlBase);
        if ($link === null) {
            return;
        }

        $this->email->enviar(
            $email,
            'Acesso aos seus projetos',
            '<p>Olá, ' . htmlspecialchars($participante['nome']) . '!</p>'
            . '<p>Clique no link abaixo pra acompanhar suas tarefas:</p>'
            . '<p><a href="' . htmlspecialchars($link) . '">Ver minhas tarefas</a></p>'
            . '<p>Esse link expira em ' . self::VALIDADE_HORAS . ' hora(s) e só pode ser usado uma vez.</p>'
        );
    }

    public function emitirLink(int $participanteId, string $urlBase): ?string
    {
        if (!$this->email->configurado()) {
            return null;
        }

        $this->invalidarPendentes($participanteId);

        $token = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', time() + self::VALIDADE_HORAS * 3600);

        $stmt = $this->pdo->prepare('INSERT INTO projetos_participante_tokens (participante_externo_id, token_hash, expira_em) VALUES (?, ?, ?)');
        $stmt->execute([$participanteId, hash('sha256', $token), $expiraEm]);

        return rtrim($urlBase, '/') . url('/projetos/portal/acessar?token=' . $token);
    }

    /** @return array{id: int, participante_externo_id: int}|null */
    public function validarToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, participante_externo_id FROM projetos_participante_tokens
             WHERE token_hash = ? AND usado_em IS NULL AND expira_em > NOW()
             LIMIT 1"
        );
        $stmt->execute([hash('sha256', $token)]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    public function marcarUsado(int $tokenId): void
    {
        $this->pdo->prepare('UPDATE projetos_participante_tokens SET usado_em = NOW() WHERE id = ?')->execute([$tokenId]);
    }

    private function invalidarPendentes(int $participanteId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE projetos_participante_tokens SET usado_em = NOW() WHERE participante_externo_id = ? AND usado_em IS NULL'
        );
        $stmt->execute([$participanteId]);
    }
}
