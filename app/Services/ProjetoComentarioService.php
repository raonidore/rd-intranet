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

    public function registrarSistema(int $projetoId, ?int $tarefaId, string $conteudo, ?int $usuarioId, ?int $participanteExternoId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO projetos_comentarios (projeto_id, tarefa_id, usuario_id, participante_externo_id, tipo, conteudo)
             VALUES (?, ?, ?, ?, 'sistema', ?)"
        );
        $stmt->execute([$projetoId, $tarefaId, $usuarioId, $participanteExternoId, $conteudo]);
    }
}
