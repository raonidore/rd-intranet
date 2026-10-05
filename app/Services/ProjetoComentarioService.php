<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Timeline do projeto -- nota manual ou linha automática ('sistema'),
 * igual chamados_externos_comentarios. Sempre presa a um projeto;
 * tarefa_id é opcional (nulo = nota geral, não presa a um cartão).
 * Autor é OU usuario_id OU participante_externo_id, nunca os dois.
 */
class ProjetoComentarioService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** Timeline cheia do projeto -- une nota+sistema de todas as tarefas com as notas gerais, em ordem cronológica. */
    public function timeline(int $projetoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, u.nome AS usuario_nome, pe.nome AS participante_nome, t.titulo AS tarefa_titulo
             FROM projetos_comentarios c
             LEFT JOIN usuarios u ON u.id = c.usuario_id
             LEFT JOIN projetos_participantes_externos pe ON pe.id = c.participante_externo_id
             LEFT JOIN projetos_tarefas t ON t.id = c.tarefa_id
             WHERE c.projeto_id = ?
             ORDER BY c.criado_em ASC, c.id ASC'
        );
        $stmt->execute([$projetoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{success: bool, message: string, id?: int} */
    public function comentar(int $projetoId, ?int $tarefaId, string $conteudo, ?int $usuarioId, ?int $participanteExternoId, ?float $latitude = null, ?float $longitude = null): array
    {
        $conteudo = trim($conteudo);
        if ($conteudo === '') {
            return ['success' => false, 'message' => 'Escreva algo antes de salvar.'];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO projetos_comentarios (projeto_id, tarefa_id, usuario_id, participante_externo_id, tipo, conteudo, latitude, longitude)
             VALUES (?, ?, ?, ?, 'nota', ?, ?, ?)"
        );
        $stmt->execute([$projetoId, $tarefaId, $usuarioId, $participanteExternoId, $conteudo, $latitude, $longitude]);

        return ['success' => true, 'message' => 'Comentário adicionado.', 'id' => (int)$this->pdo->lastInsertId()];
    }

    /*
     |---------------------------------------------------------
     | Mensagens novas -- conta só "nota" (conversa) de OUTRA pessoa, em
     | tarefas onde o usuário está envolvido (responsável ou já falou na
     | conversa), de projeto fora da lixeira. Admin que só olha não recebe
     | badge de tudo.
     |---------------------------------------------------------
     */

    private const SQL_NAO_LIDAS = "
        FROM projetos_comentarios c
        JOIN projetos_tarefas t ON t.id = c.tarefa_id
        JOIN projetos p ON p.id = t.projeto_id AND p.excluido_em IS NULL
        LEFT JOIN projetos_tarefas_leituras l ON l.tarefa_id = c.tarefa_id AND l.usuario_id = :u1
        WHERE c.tipo = 'nota'
          AND (c.usuario_id IS NULL OR c.usuario_id <> :u2)
          AND c.id > COALESCE(l.ultimo_comentario_id, 0)
          AND (EXISTS (SELECT 1 FROM projetos_tarefas_responsaveis r WHERE r.tarefa_id = c.tarefa_id AND r.usuario_id = :u3)
               OR EXISTS (SELECT 1 FROM projetos_comentarios c2 WHERE c2.tarefa_id = c.tarefa_id AND c2.usuario_id = :u4 AND c2.tipo = 'nota'))";

    /** Badge do menu Projetos. */
    public function totalNaoLidas(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) ' . self::SQL_NAO_LIDAS);
        $stmt->execute(['u1' => $usuarioId, 'u2' => $usuarioId, 'u3' => $usuarioId, 'u4' => $usuarioId]);

        return (int)$stmt->fetchColumn();
    }

    /** @return array<int, int> tarefa_id => mensagens novas, num projeto */
    public function naoLidasPorTarefa(int $projetoId, int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.tarefa_id, COUNT(*) ' . self::SQL_NAO_LIDAS . ' AND t.projeto_id = :p GROUP BY c.tarefa_id');
        $stmt->execute(['u1' => $usuarioId, 'u2' => $usuarioId, 'u3' => $usuarioId, 'u4' => $usuarioId, 'p' => $projetoId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** @return array<int, int> projeto_id => mensagens novas (lista de Projetos) */
    public function naoLidasPorProjeto(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT t.projeto_id, COUNT(*) ' . self::SQL_NAO_LIDAS . ' GROUP BY t.projeto_id');
        $stmt->execute(['u1' => $usuarioId, 'u2' => $usuarioId, 'u3' => $usuarioId, 'u4' => $usuarioId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** Abriu a tarefa (ou comentou nela): tudo que existe até agora fica lido. */
    public function marcarLido(int $tarefaId, int $usuarioId): void
    {
        $this->pdo->prepare(
            'INSERT INTO projetos_tarefas_leituras (tarefa_id, usuario_id, ultimo_comentario_id)
             SELECT ?, ?, COALESCE(MAX(id), 0) FROM projetos_comentarios WHERE tarefa_id = ?
             ON DUPLICATE KEY UPDATE ultimo_comentario_id = GREATEST(ultimo_comentario_id, VALUES(ultimo_comentario_id))'
        )->execute([$tarefaId, $usuarioId, $tarefaId]);
    }

    public function registrarSistema(int $projetoId, ?int $tarefaId, string $conteudo, ?int $usuarioId, ?int $participanteExternoId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO projetos_comentarios (projeto_id, tarefa_id, usuario_id, participante_externo_id, tipo, conteudo)
             VALUES (?, ?, ?, ?, 'sistema', ?)"
        );
        $stmt->execute([$projetoId, $tarefaId, $usuarioId, $participanteExternoId, $conteudo]);
    }
}
