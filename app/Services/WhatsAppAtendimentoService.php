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
     * Ponto de entrada único pra mensagem recebida, seja lá qual for o
     * canal (bridge QR Code, webhook da Meta ou da Twilio -- os 3
     * controllers de webhook chamam isso, em vez de cada um duplicar
     * a lógica de achar contato/atendimento e decidir se roda o bot).
     */
    public function receberMensagem(
        string $numero,
        ?string $nomeContato,
        string $texto,
        string $tipo,
        ?string $whatsappMessageId
    ): void {
        $contato = (new WhatsAppContatoService())->buscarOuCriarPorNumero($numero, $nomeContato);
        $atendimento = $this->abrirOuReaproveitar((int)$contato['id']);

        $resultado = $this->registrarMensagemEntrada((int)$atendimento['id'], $texto, $tipo, $whatsappMessageId);

        // $resultado === null quer dizer "já tinha essa mensagem" (retry
        // do provedor) -- não roda o bot de novo pra não duplicar
        // resposta. Bot só reage a texto (mídia é só logada por
        // enquanto, mesma limitação em whatsapp-bridge/index.js).
        if ($resultado !== null && $atendimento['status'] === 'bot' && $tipo === 'texto') {
            (new WhatsAppChatbotService())->processarMensagem($atendimento, $numero, $texto);
        }
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

    public function buscarComContato(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, c.numero, c.nome AS contato_nome
             FROM whatsapp_atendimentos a
             JOIN whatsapp_contatos c ON c.id = a.contato_id
             WHERE a.id = ?"
        );
        $stmt->execute([$id]);

        $atendimento = $stmt->fetch(PDO::FETCH_ASSOC);

        return $atendimento ?: null;
    }

    /**
     * Atendimentos aguardando na fila -- $setorIds = null quer dizer
     * "vê todos os setores" (admin). Pra quem não é admin, além dos
     * próprios setores, sempre entra também a "fila geral"
     * (setor_id IS NULL -- destino do chatbot quando ele não consegue
     * rotear pra nenhum setor depois de várias tentativas inválidas):
     * ninguém pode ficar "dono" de um contato sem setor nenhum, então
     * fica visível pra qualquer atendente pegar.
     */
    public function listarFila(?array $setorIds): array
    {
        $sql = "SELECT a.*, c.numero, c.nome AS contato_nome, s.nome AS setor_nome
                FROM whatsapp_atendimentos a
                JOIN whatsapp_contatos c ON c.id = a.contato_id
                LEFT JOIN whatsapp_setores s ON s.id = a.setor_id
                WHERE a.status = 'fila'";
        $params = [];

        if ($setorIds !== null) {
            if (empty($setorIds)) {
                $sql .= ' AND a.setor_id IS NULL';
            } else {
                $marcadores = implode(',', array_fill(0, count($setorIds), '?'));
                $sql .= " AND (a.setor_id IN ({$marcadores}) OR a.setor_id IS NULL)";
                $params = $setorIds;
            }
        }

        $sql .= ' ORDER BY a.aberto_em ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function assumir(int $atendimentoId, int $usuarioId): array
    {
        // A condição "AND status = 'fila'" no próprio UPDATE evita corrida:
        // se dois atendentes clicarem "Assumir" ao mesmo tempo, só o
        // primeiro UPDATE realmente casa a linha (rowCount 1), o segundo
        // não acha mais nada pra atualizar (rowCount 0).
        $stmt = $this->pdo->prepare(
            "UPDATE whatsapp_atendimentos SET status = 'em_atendimento', usuario_id = ?, atribuido_em = NOW()
             WHERE id = ? AND status = 'fila'"
        );
        $stmt->execute([$usuarioId, $atendimentoId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Esse atendimento não está mais na fila (outra pessoa já deve ter assumido).'];
        }

        return ['success' => true, 'message' => 'Atendimento assumido.'];
    }

    /**
     * Conversas abertas do próprio usuário, com prévia da última mensagem.
     */
    public function listarDoUsuario(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, c.numero, c.nome AS contato_nome,
                    (SELECT conteudo FROM whatsapp_mensagens m WHERE m.atendimento_id = a.id ORDER BY m.id DESC LIMIT 1) AS ultima_mensagem
             FROM whatsapp_atendimentos a
             JOIN whatsapp_contatos c ON c.id = a.contato_id
             WHERE a.status = 'em_atendimento' AND a.usuario_id = ?
             ORDER BY a.ultima_mensagem_em DESC"
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mensagens do atendimento -- $desdeId > 0 pra polling incremental
     * (só o que chegou depois da última mensagem já exibida na tela).
     */
    public function mensagens(int $atendimentoId, int $desdeId = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM whatsapp_mensagens WHERE atendimento_id = ? AND id > ? ORDER BY id ASC'
        );
        $stmt->execute([$atendimentoId, $desdeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Encerramento manual (atendente clica "Encerrar" no chat) -- avisa
     * o cliente com a mensagem configurada em Chatbot > Finalização
     * antes de marcar `encerrado`. Diferente do encerramento pelo
     * próprio chatbot (nó tipo 'resposta_final'), que já manda seu
     * texto específico -- essa mensagem genérica não se aplica lá.
     *
     * @return array{success: bool, message: string}
     */
    public function encerrar(int $atendimentoId): array
    {
        $atendimento = $this->buscarComContato($atendimentoId);

        if ($atendimento) {
            $mensagemFechamento = (new WhatsAppChatbotService())->renderizarTemplate(
                (new WhatsAppConfigService())->encerramentoNormal(),
                ['nome' => $atendimento['contato_nome']]
            );

            (new WhatsAppMensagemService())->enviar($atendimento['numero'], $mensagemFechamento);
            $this->registrarMensagemSaida($atendimentoId, $mensagemFechamento, 'bot');
        }

        $stmt = $this->pdo->prepare("UPDATE whatsapp_atendimentos SET status = 'encerrado', encerrado_em = NOW() WHERE id = ?");
        $stmt->execute([$atendimentoId]);

        return ['success' => true, 'message' => 'Atendimento encerrado.'];
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

    /**
     * Encerra automaticamente atendimentos sem nenhuma mensagem (entrada
     * ou saída) há mais tempo que o configurado em Chatbot >
     * Finalização -- chamado pelo cron nativo "whatsapp:encerrar-inativos"
     * (ver AtualizacaoService::garantirCronWhatsappInatividade()).
     * "Geral": vale pra qualquer status não encerrado (bot, fila,
     * em_atendimento), não só quem está preso no bot.
     *
     * @return array{encerrados: int}
     */
    public function encerrarInativos(): array
    {
        $config = new WhatsAppConfigService();
        $timeoutMinutos = $config->timeoutMinutos();
        $mensagemBase = $config->encerramentoInatividade();
        $chatbot = new WhatsAppChatbotService();

        $stmt = $this->pdo->prepare(
            "SELECT a.id, c.numero, c.nome
             FROM whatsapp_atendimentos a
             JOIN whatsapp_contatos c ON c.id = a.contato_id
             WHERE a.status != 'encerrado'
               AND a.ultima_mensagem_em < (NOW() - INTERVAL ? MINUTE)"
        );
        $stmt->execute([$timeoutMinutos]);
        $inativos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$inativos) {
            return ['encerrados' => 0];
        }

        $mensageiro = new WhatsAppMensagemService();

        foreach ($inativos as $item) {
            $mensagemFechamento = $chatbot->renderizarTemplate($mensagemBase, ['nome' => $item['nome']]);

            $mensageiro->enviar($item['numero'], $mensagemFechamento);
            $this->registrarMensagemSaida((int)$item['id'], $mensagemFechamento, 'bot');

            $this->pdo->prepare("UPDATE whatsapp_atendimentos SET status = 'encerrado', encerrado_em = NOW() WHERE id = ?")
                ->execute([$item['id']]);
        }

        return ['encerrados' => count($inativos)];
    }

    private function tocarUltimaMensagem(int $atendimentoId): void
    {
        $stmt = $this->pdo->prepare('UPDATE whatsapp_atendimentos SET ultima_mensagem_em = NOW() WHERE id = ?');
        $stmt->execute([$atendimentoId]);
    }
}
