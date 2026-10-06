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
     | Mensagens novas de OUTRA pessoa, em projeto fora da lixeira:
     | - conversa da tarefa: em tarefas onde o usuário está envolvido
     |   (responsável ou já falou na conversa) -- admin que só olha não
     |   recebe badge de tudo;
     | - nota geral do projeto: só quando ela marca o usuário com @.
     | Cada linha: id do comentário, tarefa (null = nota geral), projeto e
     | se marca o usuário.
     |---------------------------------------------------------
     */

    private const SQL_LINHAS_NAO_LIDAS = "
        SELECT c.id, c.tarefa_id, t.projeto_id,
               EXISTS (SELECT 1 FROM projetos_comentarios_mencoes m WHERE m.comentario_id = c.id AND m.usuario_id = :u5) AS mencao
        FROM projetos_comentarios c
        JOIN projetos_tarefas t ON t.id = c.tarefa_id
        JOIN projetos p ON p.id = t.projeto_id AND p.excluido_em IS NULL
        LEFT JOIN projetos_tarefas_leituras l ON l.tarefa_id = c.tarefa_id AND l.usuario_id = :u1
        WHERE c.tipo = 'nota'
          AND (c.usuario_id IS NULL OR c.usuario_id <> :u2)
          AND c.id > COALESCE(l.ultimo_comentario_id, 0)
          AND (EXISTS (SELECT 1 FROM projetos_tarefas_responsaveis r WHERE r.tarefa_id = c.tarefa_id AND r.usuario_id = :u3)
               OR EXISTS (SELECT 1 FROM projetos_comentarios c2 WHERE c2.tarefa_id = c.tarefa_id AND c2.usuario_id = :u4 AND c2.tipo = 'nota'))
        UNION ALL
        SELECT c.id, NULL, c.projeto_id, 1
        FROM projetos_comentarios c
        JOIN projetos p ON p.id = c.projeto_id AND p.excluido_em IS NULL
        LEFT JOIN projetos_leituras lp ON lp.projeto_id = c.projeto_id AND lp.usuario_id = :u6
        WHERE c.tarefa_id IS NULL AND c.tipo = 'nota'
          AND (c.usuario_id IS NULL OR c.usuario_id <> :u7)
          AND c.id > COALESCE(lp.ultimo_comentario_id, 0)
          AND EXISTS (SELECT 1 FROM projetos_comentarios_mencoes m2 WHERE m2.comentario_id = c.id AND m2.usuario_id = :u8)";

    private function parametrosNaoLidas(int $usuarioId): array
    {
        $params = [];
        for ($i = 1; $i <= 8; $i++) {
            $params["u{$i}"] = $usuarioId;
        }

        return $params;
    }

    /** @return array{total: int, mencoes: int, ultimo_id: int} -- menu Projetos (e o contador que ele consulta sozinho) */
    public function resumoNaoLidas(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(x.mencao), 0) AS mencoes, COALESCE(MAX(x.id), 0) AS ultimo_id FROM (' . self::SQL_LINHAS_NAO_LIDAS . ') x');
        $stmt->execute($this->parametrosNaoLidas($usuarioId));
        $linha = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return ['total' => (int)($linha['total'] ?? 0), 'mencoes' => (int)($linha['mencoes'] ?? 0), 'ultimo_id' => (int)($linha['ultimo_id'] ?? 0)];
    }

    public function totalNaoLidas(int $usuarioId): int
    {
        return $this->resumoNaoLidas($usuarioId)['total'];
    }

    /** @return array<int, array{total: int, mencoes: int}> tarefa_id => novas, num projeto (notas gerais ficam de fora) */
    public function naoLidasPorTarefa(int $projetoId, int $usuarioId): array
    {
        return $this->agruparNaoLidas('tarefa_id', $usuarioId, $projetoId);
    }

    /** @return array<int, array{total: int, mencoes: int}> projeto_id => novas (lista de Projetos), inclui notas gerais */
    public function naoLidasPorProjeto(int $usuarioId): array
    {
        return $this->agruparNaoLidas('projeto_id', $usuarioId, null);
    }

    private function agruparNaoLidas(string $coluna, int $usuarioId, ?int $projetoId): array
    {
        $params = $this->parametrosNaoLidas($usuarioId);
        $sql = "SELECT x.{$coluna} AS chave, COUNT(*) AS total, COALESCE(SUM(x.mencao), 0) AS mencoes FROM (" . self::SQL_LINHAS_NAO_LIDAS . ") x WHERE x.{$coluna} IS NOT NULL";
        if ($projetoId !== null) {
            $sql .= ' AND x.projeto_id = :p';
            $params['p'] = $projetoId;
        }
        $stmt = $this->pdo->prepare($sql . " GROUP BY x.{$coluna}");
        $stmt->execute($params);

        $saida = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $saida[(int)$linha['chave']] = ['total' => (int)$linha['total'], 'mencoes' => (int)$linha['mencoes']];
        }

        return $saida;
    }

    /** Notas gerais do projeto que marcam o usuário e ele ainda não viu -- destaque na Linha do tempo. @return int[] */
    public function mencoesGeraisNaoVistas(int $projetoId, int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id FROM projetos_comentarios c
             JOIN projetos_comentarios_mencoes m ON m.comentario_id = c.id AND m.usuario_id = ?
             LEFT JOIN projetos_leituras lp ON lp.projeto_id = c.projeto_id AND lp.usuario_id = ?
             WHERE c.projeto_id = ? AND c.tarefa_id IS NULL AND c.id > COALESCE(lp.ultimo_comentario_id, 0)'
        );
        $stmt->execute([$usuarioId, $usuarioId, $projetoId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Abriu o projeto: as notas gerais até agora contam como vistas. */
    public function marcarLidoProjeto(int $projetoId, int $usuarioId): void
    {
        $this->pdo->prepare(
            'INSERT INTO projetos_leituras (projeto_id, usuario_id, ultimo_comentario_id)
             SELECT ?, ?, COALESCE(MAX(id), 0) FROM projetos_comentarios WHERE projeto_id = ? AND tarefa_id IS NULL
             ON DUPLICATE KEY UPDATE ultimo_comentario_id = GREATEST(ultimo_comentario_id, VALUES(ultimo_comentario_id))'
        )->execute([$projetoId, $usuarioId, $projetoId]);
    }

    /**
     * Guarda quem foi marcado com @. $marcados vem do formulário ("interno:5",
     * "externo:3"); só vale quem pode ser marcado ali (pessoas da tarefa, ou do
     * projeto numa nota geral) e só se o "@Nome" ainda estiver no texto
     * (apagou a menção = não marca).
     *
     * @return array<int, array{tipo: string, id: int, nome: string, email: ?string}> quem ficou marcado
     */
    public function salvarMencoes(int $comentarioId, ?int $tarefaId, string $conteudo, array $marcados, ?int $projetoId = null): array
    {
        $pessoas = [];
        $lista = $tarefaId !== null ? $this->marcaveisDaTarefa($tarefaId) : $this->marcaveisDoProjeto((int)$projetoId);
        foreach ($lista as $pessoa) {
            $pessoas[$pessoa['tipo'] . ':' . (int)$pessoa['id']] = $pessoa;
        }

        $salvos = [];
        $stmt = $this->pdo->prepare('INSERT INTO projetos_comentarios_mencoes (comentario_id, usuario_id, participante_externo_id) VALUES (?, ?, ?)');
        foreach (array_unique(array_map('strval', $marcados)) as $chave) {
            $pessoa = $pessoas[$chave] ?? null;
            if ($pessoa === null || mb_stripos($conteudo, '@' . $pessoa['nome']) === false) {
                continue;
            }
            $interno = $pessoa['tipo'] === 'interno';
            $stmt->execute([$comentarioId, $interno ? (int)$pessoa['id'] : null, $interno ? null : (int)$pessoa['id']]);
            $salvos[] = $pessoa;
        }

        return $salvos;
    }

    /**
     * Quem pode ser marcado numa nota geral do projeto: quem enxerga o
     * projeto por fazer parte dele -- responsáveis de alguma tarefa, gestores
     * da área, quem criou o projeto (se for admin) e quem já escreveu nele. Externo fica de
     * fora (o portal dele é por tarefa, não veria a nota geral).
     *
     * @return array<int, array{tipo: string, id: int, nome: string, email: ?string}>
     */
    public function marcaveisDoProjeto(int $projetoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT u.id, u.nome, u.email, 'interno' AS tipo FROM usuarios u
             WHERE u.ativo = 1 AND (
                 u.id IN (SELECT r.usuario_id FROM projetos_tarefas_responsaveis r JOIN projetos_tarefas t ON t.id = r.tarefa_id WHERE t.projeto_id = ?)
                 OR u.id IN (SELECT g.usuario_id FROM projetos_areas_gestores g JOIN projetos p ON p.area_id = g.area_id WHERE p.id = ?)
                 OR (u.perfil = 'admin' AND u.id = (SELECT criado_por FROM projetos WHERE id = ?)) -- só admin enxerga o projeto sem ser gestor/responsável
                 OR u.id IN (SELECT c.usuario_id FROM projetos_comentarios c WHERE c.projeto_id = ? AND c.tipo = 'nota' AND c.usuario_id IS NOT NULL)
             )
             ORDER BY u.nome"
        );
        $stmt->execute([$projetoId, $projetoId, $projetoId, $projetoId]);

        return array_map(fn (array $u) => ['id' => (int)$u['id']] + $u, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Quem pode ser marcado com @ na tarefa: as pessoas dela + quem já
     * escreveu na conversa (ex.: o gestor que puxou o assunto sem ser
     * responsável -- sem isso, quem respondia não conseguia marcá-lo de volta).
     *
     * @return array<int, array{tipo: string, id: int, nome: string, email: ?string}>
     */
    public function marcaveisDaTarefa(int $tarefaId): array
    {
        $lista = [];
        foreach ((new ProjetoTarefaService())->pessoas($tarefaId) as $pessoa) {
            $lista[$pessoa['tipo'] . ':' . (int)$pessoa['id']] = $pessoa;
        }

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT u.id, u.nome, u.email FROM projetos_comentarios c JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.tarefa_id = ? AND c.tipo = 'nota' AND u.ativo = 1"
        );
        $stmt->execute([$tarefaId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $autor) {
            $lista['interno:' . (int)$autor['id']] ??= ['id' => (int)$autor['id'], 'nome' => $autor['nome'], 'email' => $autor['email'], 'tipo' => 'interno'];
        }

        return array_values($lista);
    }

    /**
     * Quem foi @mencionado na DESCRIÇÃO da tarefa: vira uma mensagem na
     * conversa ("Mencionou @Fulano na descrição da tarefa.") com as menções --
     * aí entra no mesmo aviso de sempre (menu, card, lista, e-mail).
     *
     * @param string[] $chaves "interno:ID"/"externo:ID"
     */
    public function avisarMencaoNaDescricao(int $projetoId, int $tarefaId, array $chaves, ?int $usuarioId): void
    {
        $nomes = [];
        foreach ($this->marcaveisDaTarefa($tarefaId) as $pessoa) {
            $chave = $pessoa['tipo'] . ':' . (int)$pessoa['id'];
            if (in_array($chave, $chaves, true) && !($pessoa['tipo'] === 'interno' && (int)$pessoa['id'] === (int)$usuarioId)) {
                $nomes[$chave] = $pessoa['nome'];
            }
        }
        if (!$nomes) {
            return;
        }

        $conteudo = 'Mencionou ' . implode(', ', array_map(fn ($n) => '@' . $n, $nomes)) . ' na descrição da tarefa.';
        $resultado = $this->comentar($projetoId, $tarefaId, $conteudo, $usuarioId, null);
        if (!$resultado['success']) {
            return;
        }
        $this->salvarMencoes((int)$resultado['id'], $tarefaId, $conteudo, array_keys($nomes));
        if ($usuarioId !== null) {
            $this->marcarLido($tarefaId, $usuarioId);
        }
        (new ProjetoNotificacaoService())->notificarComentario((int)$resultado['id']);
    }

    /** @return string[] chaves "interno:ID"/"externo:ID" marcadas na mensagem */
    public function mencoesDoComentario(int $comentarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT usuario_id, participante_externo_id FROM projetos_comentarios_mencoes WHERE comentario_id = ?');
        $stmt->execute([$comentarioId]);

        return array_map(
            fn (array $m) => $m['usuario_id'] !== null ? 'interno:' . (int)$m['usuario_id'] : 'externo:' . (int)$m['participante_externo_id'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
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
