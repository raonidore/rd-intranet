<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Núcleo do atendimento: abrir/reaproveitar a conversa de um contato e
 * registrar mensagens. A orquestração de fila/chatbot (pra onde o
 * atendimento vai depois de aberto) entra nas próximas fases -- por
 * enquanto todo atendimento novo nasce em status 'bot' e fica lá.
 */
class WhatsAppAtendimentoService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Atendimento ainda não encerrado mais recente do contato, ou cria
     * um novo (status inicial 'bot').
     */
    public function abrirOuReaproveitar(int $contatoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM whatsapp_atendimentos WHERE contato_id = ? AND status != 'encerrado' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$contatoId]);
        $atendimento = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($atendimento) {
            return $atendimento;
        }

        $ins = $this->pdo->prepare("INSERT INTO whatsapp_atendimentos (contato_id, status) VALUES (?, 'bot')");
        $ins->execute([$contatoId]);

        return $this->buscar((int)$this->pdo->lastInsertId());
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_atendimentos WHERE id = ?');
        $stmt->execute([$id]);

        $atendimento = $stmt->fetch(PDO::FETCH_ASSOC);

        return $atendimento ?: null;
    }

    /**
     * @return array{id: int}|null a mensagem já existia (mesmo
     *   whatsapp_message_id) -- retorna null pra sinalizar "não gravou
     *   de novo", já que o bridge pode reenviar o mesmo evento em caso
     *   de retry de rede.
     */
    public function registrarMensagemEntrada(
        int $atendimentoId,
        string $conteudo,
        string $tipo = 'texto',
        ?string $whatsappMessageId = null
    ): ?array {
        if ($whatsappMessageId !== null) {
            $existe = $this->pdo->prepare('SELECT id FROM whatsapp_mensagens WHERE whatsapp_message_id = ?');
            $existe->execute([$whatsappMessageId]);
            if ($existe->fetch()) {
                return null;
            }
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO whatsapp_mensagens (atendimento_id, direcao, tipo, conteudo, origem, whatsapp_message_id)
             VALUES (?, 'entrada', ?, ?, 'cliente', ?)"
        );
        $stmt->execute([$atendimentoId, $tipo, $conteudo, $whatsappMessageId]);

        // lastInsertId() precisa ser lido ANTES do UPDATE de
        // tocarUltimaMensagem() -- confirmado ao vivo: nesse driver
        // (mysqlnd), qualquer statement seguinte, mesmo um UPDATE sem
        // auto_increment nenhum, zera o valor (diferente da função SQL
        // LAST_INSERT_ID(), que preserva entre statements).
        $id = (int)$this->pdo->lastInsertId();

        $this->tocarUltimaMensagem($atendimentoId);

        return ['id' => $id];
    }

    public function registrarMensagemSaida(
        int $atendimentoId,
        string $conteudo,
        string $origem = 'usuario',
        ?int $usuarioId = null,
        string $tipo = 'texto'
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO whatsapp_mensagens (atendimento_id, direcao, tipo, conteudo, origem, usuario_id)
             VALUES (?, 'saida', ?, ?, ?, ?)"
        );
        $stmt->execute([$atendimentoId, $tipo, $conteudo, $origem, $usuarioId]);

        $id = (int)$this->pdo->lastInsertId();

        $this->tocarUltimaMensagem($atendimentoId);

        return $id;
    }

    private function tocarUltimaMensagem(int $atendimentoId): void
    {
        $stmt = $this->pdo->prepare('UPDATE whatsapp_atendimentos SET ultima_mensagem_em = NOW() WHERE id = ?');
        $stmt->execute([$atendimentoId]);
    }
}
