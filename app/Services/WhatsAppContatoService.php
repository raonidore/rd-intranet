<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class WhatsAppContatoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Acha o contato pelo número (dígitos só) ou cria um novo -- ponto de
     * entrada usado pelo webhook toda vez que chega mensagem de um
     * número ainda não visto antes.
     */
    public function buscarOuCriarPorNumero(string $numero, ?string $nome = null): array
    {
        $numero = $this->normalizarNumero($numero);
        $nome = $nome !== null ? trim($nome) : null;
        if ($nome === '') {
            $nome = null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contatos WHERE numero = ?');
        $stmt->execute([$numero]);
        $contato = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contato) {
            if ($nome !== null && $nome !== $contato['nome']) {
                $upd = $this->pdo->prepare('UPDATE whatsapp_contatos SET nome = ? WHERE id = ?');
                $upd->execute([$nome, $contato['id']]);
                $contato['nome'] = $nome;
            }

            return $contato;
        }

        $ins = $this->pdo->prepare('INSERT INTO whatsapp_contatos (numero, nome) VALUES (?, ?)');
        $ins->execute([$numero, $nome]);

        return ['id' => (int)$this->pdo->lastInsertId(), 'numero' => $numero, 'nome' => $nome];
    }

    private function normalizarNumero(string $numero): string
    {
        return preg_replace('/\D+/', '', $numero) ?? '';
    }
}
