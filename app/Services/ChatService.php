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
                    (SELECT CASE
                                WHEN m.tipo = 'imagem' THEN '📷 Imagem'
                                WHEN m.tipo = 'audio' THEN '🎤 Áudio'
                                WHEN m.tipo = 'documento' THEN CONCAT('📎 ', COALESCE(NULLIF(m.conteudo, ''), 'Documento'))
                                ELSE m.conteudo
                            END
                     FROM chat_mensagens m WHERE m.conversa_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultima_mensagem,
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

    /** @return array<int, array{id:int, nome:string, login:string, ultimo_acesso:?string}> */
    public function participantes(int $conversaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.nome, u.login, u.ultimo_acesso
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

    /**
     * $tipo/$midiaPath só quando a mensagem é um anexo (imagem/áudio/
     * documento) -- nesse caso $conteudo é a legenda, pode vir vazia.
     * Menção (@login de alguém que participa desta conversa) é
     * extraída do texto e gravada em chat_mencoes -- só quem já está
     * na conversa pode ser mencionado, não dá pra "invocar" gente de
     * fora por engano.
     *
     * @return array{success: bool, message?: string, id?: int}
     */
    public function enviar(int $conversaId, int $usuarioId, string $conteudo, string $tipo = 'texto', ?string $midiaPath = null): array
    {
        $conteudo = trim($conteudo);
        if ($conteudo === '' && $tipo === 'texto') {
            return ['success' => false, 'message' => 'Escreva alguma coisa antes de enviar.'];
        }

        if (!$this->ehParticipante($conversaId, $usuarioId)) {
            return ['success' => false, 'message' => 'Conversa não encontrada.'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO chat_mensagens (conversa_id, usuario_id, conteudo, tipo, midia_path) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$conversaId, $usuarioId, $conteudo, $tipo, $midiaPath]);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('UPDATE chat_conversas SET ultima_mensagem_em = NOW() WHERE id = ?')->execute([$conversaId]);

        // A própria mensagem não conta como "não lida" pra quem mandou.
        $this->marcarComoLida($conversaId, $usuarioId);

        if ($conteudo !== '') {
            $this->registrarMencoes($id, $conversaId, $usuarioId, $conteudo);
        }

        return ['success' => true, 'id' => $id];
    }

    private function registrarMencoes(int $mensagemId, int $conversaId, int $autorId, string $conteudo): void
    {
        if (!preg_match_all('/@([a-zA-Z0-9._-]+)/', $conteudo, $encontrados)) {
            return;
        }

        $loginsMencionados = array_unique(array_map('mb_strtolower', $encontrados[1]));
        if (empty($loginsMencionados)) {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO chat_mencoes (mensagem_id, usuario_id) VALUES (?, ?)');
        foreach ($this->participantes($conversaId) as $participante) {
            if ((int)$participante['id'] === $autorId) {
                continue;
            }
            if (in_array(mb_strtolower($participante['login']), $loginsMencionados, true)) {
                $stmt->execute([$mensagemId, $participante['id']]);
            }
        }
    }

    public function marcarComoLida(int $conversaId, int $usuarioId): void
    {
        $this->pdo->prepare('UPDATE chat_participantes SET ultima_leitura_em = NOW() WHERE conversa_id = ? AND usuario_id = ?')
            ->execute([$conversaId, $usuarioId]);
    }

    /** @param int $paraUsuarioId usado só pra marcar "mencionadoEu" em cada mensagem */
    public function mensagens(int $conversaId, int $desde = 0, int $paraUsuarioId = 0): array
    {
        $sql = "SELECT m.*, u.nome AS usuario_nome,
                       EXISTS(SELECT 1 FROM chat_mencoes cm WHERE cm.mensagem_id = m.id AND cm.usuario_id = ?) AS mencionado_eu
                FROM chat_mensagens m
                JOIN usuarios u ON u.id = m.usuario_id
                WHERE m.conversa_id = ?";
        $params = [$paraUsuarioId, $conversaId];

        if ($desde > 0) {
            $sql .= ' AND m.id > ?';
            $params[] = $desde;
        }

        $sql .= ' ORDER BY m.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($linhas as &$linha) {
            $linha['mencionado_eu'] = (bool)$linha['mencionado_eu'];
        }

        return $linhas;
    }

    public function buscarMensagem(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM chat_mensagens WHERE id = ?');
        $stmt->execute([$id]);

        $mensagem = $stmt->fetch(PDO::FETCH_ASSOC);

        return $mensagem ?: null;
    }

    /**
     * Liga/desliga a reação do usuário -- clicar de novo no mesmo emoji
     * remove (toggle), igual todo reagir-a-mensagem de app de chat.
     *
     * @return array{success: bool, message?: string, ligou?: bool}
     */
    public function reagir(int $mensagemId, int $usuarioId, string $emoji): array
    {
        $mensagem = $this->buscarMensagem($mensagemId);
        if (!$mensagem || !$this->ehParticipante((int)$mensagem['conversa_id'], $usuarioId)) {
            return ['success' => false, 'message' => 'Mensagem não encontrada.'];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM chat_reacoes WHERE mensagem_id = ? AND usuario_id = ? AND emoji = ?');
        $stmt->execute([$mensagemId, $usuarioId, $emoji]);
        $existente = $stmt->fetchColumn();

        if ($existente) {
            $this->pdo->prepare('DELETE FROM chat_reacoes WHERE id = ?')->execute([$existente]);
            return ['success' => true, 'ligou' => false];
        }

        $this->pdo->prepare('INSERT INTO chat_reacoes (mensagem_id, usuario_id, emoji) VALUES (?, ?, ?)')
            ->execute([$mensagemId, $usuarioId, $emoji]);

        return ['success' => true, 'ligou' => true];
    }

    /** @return array<int, array<int, array{emoji: string, total: int, reagiuEu: bool}>> mensagem_id => [reação, ...] */
    public function reacoesPorConversa(int $conversaId, int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.mensagem_id, r.emoji, COUNT(*) AS total, SUM(r.usuario_id = ?) AS reagiu_eu
             FROM chat_reacoes r
             JOIN chat_mensagens m ON m.id = r.mensagem_id
             WHERE m.conversa_id = ?
             GROUP BY r.mensagem_id, r.emoji
             ORDER BY r.mensagem_id, MIN(r.id)'
        );
        $stmt->execute([$usuarioId, $conversaId]);

        $porMensagem = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $porMensagem[(int)$linha['mensagem_id']][] = [
                'emoji' => $linha['emoji'],
                'total' => (int)$linha['total'],
                'reagiuEu' => (bool)$linha['reagiu_eu'],
            ];
        }

        return $porMensagem;
    }

    /**
     * Busca por texto nas conversas do usuário -- só nas que ele
     * participa (sem exceção pra admin: histórico de conversa é
     * privado mesmo pra quem administra o sistema).
     */
    public function buscarMensagensDoUsuario(int $usuarioId, string $termo, int $limite = 50): array
    {
        $termo = trim($termo);
        if ($termo === '') {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.*, u.nome AS usuario_nome,
                    CASE WHEN c.tipo = 'grupo' THEN c.nome ELSE ou.nome END AS conversa_nome_exibicao
             FROM chat_mensagens m
             JOIN chat_participantes p ON p.conversa_id = m.conversa_id AND p.usuario_id = ?
             JOIN chat_conversas c ON c.id = m.conversa_id
             JOIN usuarios u ON u.id = m.usuario_id
             LEFT JOIN chat_participantes op ON op.conversa_id = c.id AND op.usuario_id != ? AND c.tipo = 'direta'
             LEFT JOIN usuarios ou ON ou.id = op.usuario_id
             WHERE m.conteudo LIKE ?
             ORDER BY m.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(2, $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(3, '%' . str_replace(['%', '_'], ['\%', '\_'], $termo) . '%');
        $stmt->bindValue(4, $limite, PDO::PARAM_INT);
        $stmt->execute();

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
