<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Chat interno -- Fase 1 (MVP por polling). Conversa direta (1:1,
 * sempre exatamente 2 participantes) e em grupo. PDO puro, mesmo
 * estilo do domínio WhatsApp/Chamados. Presença reaproveita
 * UsuarioOnlineService (usuarios.ultimo_acesso) -- nenhuma tabela nova
 * pra isso. "Não lida" é medido comparando
 * chat_participantes.ultima_leitura_em contra chat_mensagens.criado_em,
 * sem recibo de leitura por mensagem (fica pra uma fase futura, se
 * precisar).
 */
class ChatService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Conversas do usuário, mais recentes primeiro -- nome de exibição
     * já resolvido (nome do grupo, ou nome do outro participante numa
     * direta), com prévia da última mensagem e contagem de não lidas.
     */
    public function listarConversas(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    CASE WHEN c.tipo = 'grupo' THEN c.nome ELSE ou.nome END AS nome_exibicao,
                    CASE WHEN c.tipo = 'direta' THEN ou.id ELSE NULL END AS outro_usuario_id,
                    (SELECT conteudo FROM chat_mensagens m WHERE m.conversa_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultima_mensagem,
                    (SELECT COUNT(*) FROM chat_mensagens m2
                     WHERE m2.conversa_id = c.id AND m2.usuario_id != ?
                       AND (p.ultima_leitura_em IS NULL OR m2.criado_em > p.ultima_leitura_em)) AS nao_lidas
             FROM chat_conversas c
             JOIN chat_participantes p ON p.conversa_id = c.id AND p.usuario_id = ?
             LEFT JOIN chat_participantes op ON op.conversa_id = c.id AND op.usuario_id != ? AND c.tipo = 'direta'
             LEFT JOIN usuarios ou ON ou.id = op.usuario_id
             ORDER BY c.ultima_mensagem_em DESC"
        );
        $stmt->execute([$usuarioId, $usuarioId, $usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarConversa(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chat_conversas WHERE id = ?');
        $stmt->execute([$id]);

        $conversa = $stmt->fetch(PDO::FETCH_ASSOC);

        return $conversa ?: null;
    }

    public function ehParticipante(int $conversaId, int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM chat_participantes WHERE conversa_id = ? AND usuario_id = ?');
        $stmt->execute([$conversaId, $usuarioId]);

        return (bool)$stmt->fetchColumn();
    }

    /** @return array<int, array{id:int, nome:string, ultimo_acesso:?string}> */
    public function participantes(int $conversaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.nome, u.ultimo_acesso
             FROM chat_participantes p
             JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.conversa_id = ?
             ORDER BY u.nome'
        );
        $stmt->execute([$conversaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Acha a conversa direta já existente entre os dois (nunca duplica
     * DM) ou cria uma nova -- direta sempre tem exatamente 2
     * participantes, por isso o find funciona só cruzando as duas
     * linhas de chat_participantes.
     *
     * @return array{success: bool, message?: string, id?: int}
     */
    public function criarOuBuscarDireta(int $usuarioId, int $outroUsuarioId): array
    {
        if ($usuarioId === $outroUsuarioId) {
            return ['success' => false, 'message' => 'Não é possível iniciar uma conversa consigo mesmo.'];
        }

        $stmt = $this->pdo->prepare(
            "SELECT p1.conversa_id
             FROM chat_participantes p1
             JOIN chat_participantes p2 ON p2.conversa_id = p1.conversa_id AND p2.usuario_id = ?
             JOIN chat_conversas c ON c.id = p1.conversa_id AND c.tipo = 'direta'
             WHERE p1.usuario_id = ?
             LIMIT 1"
        );
        $stmt->execute([$outroUsuarioId, $usuarioId]);
        $existente = $stmt->fetchColumn();

        if ($existente) {
            return ['success' => true, 'id' => (int)$existente];
        }

        $stmtOutro = $this->pdo->prepare('SELECT id FROM usuarios WHERE id = ? AND ativo = 1');
        $stmtOutro->execute([$outroUsuarioId]);
        if (!$stmtOutro->fetchColumn()) {
            return ['success' => false, 'message' => 'Usuário não encontrado.'];
        }

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare("INSERT INTO chat_conversas (tipo, criado_por) VALUES ('direta', ?)")->execute([$usuarioId]);
            $conversaId = (int)$this->pdo->lastInsertId();

            $ins = $this->pdo->prepare('INSERT INTO chat_participantes (conversa_id, usuario_id) VALUES (?, ?)');
            $ins->execute([$conversaId, $usuarioId]);
            $ins->execute([$conversaId, $outroUsuarioId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao criar conversa.'];
        }

        return ['success' => true, 'id' => $conversaId];
    }

    /**
     * @param int[] $participanteIds
     * @return array{success: bool, message?: string, id?: int}
     */
    public function criarGrupo(string $nome, int $criadoPor, array $participanteIds): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Dê um nome pro grupo.'];
        }

        $participanteIds = array_values(array_unique(array_map('intval', $participanteIds)));
        $participanteIds = array_values(array_filter($participanteIds, fn (int $id) => $id !== $criadoPor));

        if (empty($participanteIds)) {
            return ['success' => false, 'message' => 'Escolha pelo menos mais uma pessoa pro grupo.'];
        }

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare("INSERT INTO chat_conversas (tipo, nome, criado_por) VALUES ('grupo', ?, ?)")->execute([$nome, $criadoPor]);
            $conversaId = (int)$this->pdo->lastInsertId();

            $ins = $this->pdo->prepare('INSERT INTO chat_participantes (conversa_id, usuario_id) VALUES (?, ?)');
            $ins->execute([$conversaId, $criadoPor]);
            foreach ($participanteIds as $participanteId) {
                $ins->execute([$conversaId, $participanteId]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Falha ao criar grupo.'];
        }

        return ['success' => true, 'id' => $conversaId];
    }

    /** @return array{success: bool, message?: string, id?: int} */
    public function enviar(int $conversaId, int $usuarioId, string $conteudo): array
    {
        $conteudo = trim($conteudo);
        if ($conteudo === '') {
            return ['success' => false, 'message' => 'Escreva alguma coisa antes de enviar.'];
        }

        if (!$this->ehParticipante($conversaId, $usuarioId)) {
            return ['success' => false, 'message' => 'Conversa não encontrada.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO chat_mensagens (conversa_id, usuario_id, conteudo) VALUES (?, ?, ?)');
        $stmt->execute([$conversaId, $usuarioId, $conteudo]);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('UPDATE chat_conversas SET ultima_mensagem_em = NOW() WHERE id = ?')->execute([$conversaId]);

        // A própria mensagem não conta como "não lida" pra quem mandou.
        $this->marcarComoLida($conversaId, $usuarioId);

        return ['success' => true, 'id' => $id];
    }

    public function marcarComoLida(int $conversaId, int $usuarioId): void
    {
        $this->pdo->prepare('UPDATE chat_participantes SET ultima_leitura_em = NOW() WHERE conversa_id = ? AND usuario_id = ?')
            ->execute([$conversaId, $usuarioId]);
    }

    public function mensagens(int $conversaId, int $desde = 0): array
    {
        $sql = "SELECT m.*, u.nome AS usuario_nome
                FROM chat_mensagens m
                JOIN usuarios u ON u.id = m.usuario_id
                WHERE m.conversa_id = ?";
        $params = [$conversaId];

        if ($desde > 0) {
            $sql .= ' AND m.id > ?';
            $params[] = $desde;
        }

        $sql .= ' ORDER BY m.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Total de mensagens não lidas do usuário, somando todas as conversas -- badge do menu. */
    public function contarNaoLidas(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM chat_mensagens m
             JOIN chat_participantes p ON p.conversa_id = m.conversa_id AND p.usuario_id = ?
             WHERE m.usuario_id != ? AND (p.ultima_leitura_em IS NULL OR m.criado_em > p.ultima_leitura_em)"
        );
        $stmt->execute([$usuarioId, $usuarioId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Maior id de mensagem recebida (não mandada por mim) em qualquer
     * conversa -- gatilho do alerta sonoro, mesmo raciocínio do
     * WhatsApp/Chamados (id crescente detecta mensagem repetida na
     * mesma conversa, contagem sozinha não detectaria).
     */
    public function ultimoIdMensagemRecebida(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(m.id), 0) FROM chat_mensagens m
             JOIN chat_participantes p ON p.conversa_id = m.conversa_id AND p.usuario_id = ?
             WHERE m.usuario_id != ?"
        );
        $stmt->execute([$usuarioId, $usuarioId]);

        return (int)$stmt->fetchColumn();
    }

    /** Usuários ativos disponíveis pra iniciar conversa/grupo (todo mundo exceto quem está logado). */
    public function usuariosDisponiveis(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, login FROM usuarios WHERE ativo = 1 AND id != ? ORDER BY nome');
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
