<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Respostas prontas acionadas por "/comando" na caixa de resposta do
 * atendente (app/Views/whatsapp/atendimento_chat.php) -- CRUD simples,
 * mesmo padrão do WhatsAppSetorService.
 */
class WhatsAppMensagemRapidaService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listar(): array
    {
        return $this->pdo->query('SELECT * FROM whatsapp_mensagens_rapidas ORDER BY comando')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_mensagens_rapidas WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function criar(string $comando, string $mensagem): array
    {
        $erro = $this->validar($comando, $mensagem);
        if ($erro) {
            return $erro;
        }

        $comando = $this->normalizarComando($comando);

        if ($this->comandoEmUso($comando)) {
            return ['success' => false, 'message' => 'Já existe uma mensagem rápida com esse comando.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO whatsapp_mensagens_rapidas (comando, mensagem) VALUES (?, ?)');
        $stmt->execute([$comando, trim($mensagem)]);

        return ['success' => true, 'message' => 'Mensagem rápida criada.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function atualizar(int $id, string $comando, string $mensagem): array
    {
        $erro = $this->validar($comando, $mensagem);
        if ($erro) {
            return $erro;
        }

        $comando = $this->normalizarComando($comando);

        if ($this->comandoEmUso($comando, $id)) {
            return ['success' => false, 'message' => 'Já existe uma mensagem rápida com esse comando.'];
        }

        $stmt = $this->pdo->prepare('UPDATE whatsapp_mensagens_rapidas SET comando = ?, mensagem = ? WHERE id = ?');
        $stmt->execute([$comando, trim($mensagem), $id]);

        return ['success' => true, 'message' => 'Mensagem rápida atualizada.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function excluir(int $id): array
    {
        $stmt = $this->pdo->prepare('DELETE FROM whatsapp_mensagens_rapidas WHERE id = ?');
        $stmt->execute([$id]);

        return ['success' => true, 'message' => 'Mensagem rápida removida.'];
    }

    private function normalizarComando(string $comando): string
    {
        $comando = strtolower(trim($comando));

        return ltrim($comando, '/');
    }

    private function validar(string $comando, string $mensagem): ?array
    {
        if (trim($comando) === '' || trim($mensagem) === '') {
            return ['success' => false, 'message' => 'Preencha o comando e a mensagem.'];
        }

        if (!preg_match('/^\/?[a-z0-9_-]+$/i', trim($comando))) {
            return ['success' => false, 'message' => 'O comando só pode ter letras, números, "-" e "_" (sem espaços).'];
        }

        return null;
    }

    private function comandoEmUso(string $comando, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM whatsapp_mensagens_rapidas WHERE comando = ?';
        $params = [$comando];

        if ($ignorarId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}
