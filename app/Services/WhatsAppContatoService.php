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

    /**
     * Normaliza um número digitado à mão (ex: "(83) 99104-3598") pro
     * mesmo formato usado internamente (DDI 55 + DDD + número, só
     * dígitos) -- aceita tanto com quanto sem o "55" na frente (mais
     * natural pra quem tá digitando um número local). Retorna null se
     * não bater com o formato esperado (nem 10/11 dígitos locais, nem
     * 12/13 já com DDI).
     */
    public function normalizarNumeroBr(string $entrada): ?string
    {
        $digitos = $this->normalizarNumero($entrada);

        if (preg_match('/^\d{10,11}$/', $digitos)) {
            $digitos = '55' . $digitos;
        }

        return preg_match('/^55\d{10,11}$/', $digitos) ? $digitos : null;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contatos WHERE id = ?');
        $stmt->execute([$id]);

        $contato = $stmt->fetch(PDO::FETCH_ASSOC);

        return $contato ?: null;
    }

    private function normalizarNumero(string $numero): string
    {
        return preg_replace('/\D+/', '', $numero) ?? '';
    }
}
