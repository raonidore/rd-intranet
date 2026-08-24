<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Mural de Avisos -- informação rápida no Dashboard. Direcionamento
 * reaproveita Grupos (grupo_usuarios) e usuários diretos, combináveis
 * no mesmo aviso (ver avisos_destinatarios). Confirmação de leitura é
 * OPCIONAL por aviso (confirmacao_obrigatoria) -- quando ligada, abrir
 * o aviso marca "visto" (some do contador de não lidos) mas só o
 * clique explícito em "Confirmar que li" preenche confirmado_em.
 */
class AvisoService
{
    private const SEVERIDADES_VALIDAS = ['informativo', 'atencao', 'urgente'];

    private PDO $pdo;
    private GrupoService $grupoService;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->grupoService = new GrupoService();
    }

    /** Listagem administrativa -- TODOS os avisos, sem filtrar por direcionamento (isso é feito em listarVisiveisParaUsuario). */
    public function listar(): array
    {
        return $this->pdo->query(
            "SELECT a.*, u.nome AS criado_por_nome,
                    (SELECT COUNT(*) FROM avisos_leituras l WHERE l.aviso_id = a.id) AS total_vistos,
                    (SELECT COUNT(*) FROM avisos_leituras l WHERE l.aviso_id = a.id AND l.confirmado_em IS NOT NULL) AS total_confirmados
             FROM avisos a
             LEFT JOIN usuarios u ON u.id = a.criado_por
             ORDER BY a.fixado DESC, a.criado_em DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM avisos WHERE id = ?');
        $stmt->execute([$id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        return $item ?: null;
    }

    /**
     * Mural pessoal -- só os avisos de verdade endereçados a este usuário
     * (Todos, um dos grupos dele, ou ele diretamente). Aplica igual pra
     * admin também -- é sobre relevância, não sobre segurança (admin
     * enxerga tudo sem filtro na tela de gerenciamento, não aqui).
     */
    public function listarVisiveisParaUsuario(int $usuarioId, ?int $limite = null): array
    {
        [$condicao, $params] = $this->condicaoDestinatario($usuarioId);

        $sql = "SELECT DISTINCT a.*, l.visto_em, l.confirmado_em
                FROM avisos a
                JOIN avisos_destinatarios d ON d.aviso_id = a.id
                LEFT JOIN avisos_leituras l ON l.aviso_id = a.id AND l.usuario_id = ?
                WHERE a.ativo = 1 AND ({$condicao})
                ORDER BY a.fixado DESC, a.criado_em DESC";

        if ($limite !== null) {
            $sql .= ' LIMIT ' . (int)$limite;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId, ...$params]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarNaoLidos(int $usuarioId): int
    {
        [$condicao, $params] = $this->condicaoDestinatario($usuarioId);

        $sql = "SELECT COUNT(DISTINCT a.id)
                FROM avisos a
                JOIN avisos_destinatarios d ON d.aviso_id = a.id
                LEFT JOIN avisos_leituras l ON l.aviso_id = a.id AND l.usuario_id = ?
                WHERE a.ativo = 1 AND l.id IS NULL AND ({$condicao})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId, ...$params]);

        return (int)$stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array} SQL da condição de destinatário + parâmetros, na ordem em que aparecem. */
    private function condicaoDestinatario(int $usuarioId): array
    {
        $grupoIds = $this->grupoService->idsGruposDoUsuario($usuarioId);

        $condicao = "d.tipo = 'todos' OR (d.tipo = 'usuario' AND d.destinatario_id = ?)";
        $params = [$usuarioId];

        if ($grupoIds) {
            $marcadores = implode(',', array_fill(0, count($grupoIds), '?'));
            $condicao .= " OR (d.tipo = 'grupo' AND d.destinatario_id IN ({$marcadores}))";
            $params = array_merge($params, $grupoIds);
        }

        return [$condicao, $params];
    }

    public function destinatariosDoAviso(int $avisoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM avisos_destinatarios WHERE aviso_id = ?');
        $stmt->execute([$avisoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array $dados titulo, conteudo, severidade, fixado, confirmacao_obrigatoria, usuario_id (autor)
     * @param array<int, array{tipo:string, id?:int}> $destinatarios
     * @return array{success: bool, message: string, id?: int}
     */
    public function criar(array $dados, array $destinatarios): array
    {
        $erro = $this->validar($dados, $destinatarios);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO avisos (titulo, conteudo, severidade, fixado, confirmacao_obrigatoria, criado_por) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($dados['titulo']),
            trim($dados['conteudo']),
            in_array($dados['severidade'] ?? '', self::SEVERIDADES_VALIDAS, true) ? $dados['severidade'] : 'informativo',
            !empty($dados['fixado']) ? 1 : 0,
            !empty($dados['confirmacao_obrigatoria']) ? 1 : 0,
            $dados['usuario_id'] ?? null,
        ]);

        $id = (int)$this->pdo->lastInsertId();
        $this->salvarDestinatarios($id, $destinatarios);

        return ['success' => true, 'message' => 'Aviso publicado.', 'id' => $id];
    }

    /** @return array{success: bool, message: string} */
    public function atualizar(int $id, array $dados, array $destinatarios): array
    {
        $erro = $this->validar($dados, $destinatarios);
        if ($erro) {
            return ['success' => false, 'message' => $erro];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE avisos SET titulo = ?, conteudo = ?, severidade = ?, fixado = ?, confirmacao_obrigatoria = ?, ativo = ? WHERE id = ?'
        );
        $stmt->execute([
            trim($dados['titulo']),
            trim($dados['conteudo']),
            in_array($dados['severidade'] ?? '', self::SEVERIDADES_VALIDAS, true) ? $dados['severidade'] : 'informativo',
            !empty($dados['fixado']) ? 1 : 0,
            !empty($dados['confirmacao_obrigatoria']) ? 1 : 0,
            !empty($dados['ativo']) ? 1 : 0,
            $id,
        ]);

        $this->salvarDestinatarios($id, $destinatarios);

        return ['success' => true, 'message' => 'Aviso atualizado.'];
    }

    /** @return array{success: bool, message: string} */
    public function excluir(int $id): array
    {
        $this->pdo->prepare('DELETE FROM avisos WHERE id = ?')->execute([$id]);

        return ['success' => true, 'message' => 'Aviso removido.'];
    }

    public function marcarVisto(int $avisoId, int $usuarioId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO avisos_leituras (aviso_id, usuario_id) VALUES (?, ?)');
        $stmt->execute([$avisoId, $usuarioId]);
    }

    /** @return array{success: bool, message: string} */
    public function confirmarLeitura(int $avisoId, int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO avisos_leituras (aviso_id, usuario_id, confirmado_em) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE confirmado_em = NOW()'
        );
        $stmt->execute([$avisoId, $usuarioId]);

        return ['success' => true, 'message' => 'Leitura confirmada.'];
    }

    /** @return int[] ids de usuarios ativos alcançados por este aviso (resolve grupo -> usuários, sem duplicar). */
    public function usuariosAlvo(int $avisoId): array
    {
        $destinatarios = $this->destinatariosDoAviso($avisoId);

        foreach ($destinatarios as $d) {
            if ($d['tipo'] === 'todos') {
                return array_map('intval', $this->pdo->query('SELECT id FROM usuarios WHERE ativo = 1')->fetchAll(PDO::FETCH_COLUMN));
            }
        }

        $usuarioIds = [];
        foreach ($destinatarios as $d) {
            if ($d['tipo'] === 'usuario') {
                $usuarioIds[] = (int)$d['destinatario_id'];
            } elseif ($d['tipo'] === 'grupo') {
                $usuarioIds = array_merge($usuarioIds, $this->grupoService->idsUsuariosDoGrupo((int)$d['destinatario_id']));
            }
        }
        $usuarioIds = array_values(array_unique($usuarioIds));

        if (!$usuarioIds) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($usuarioIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE ativo = 1 AND id IN ({$marcadores})");
        $stmt->execute($usuarioIds);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Relatório de leitura -- quem foi alcançado, se já viu e se já confirmou (quando o aviso exige confirmação). */
    public function relatorioLeitura(int $avisoId): array
    {
        $usuarioIds = $this->usuariosAlvo($avisoId);
        if (!$usuarioIds) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($usuarioIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.nome, u.login, l.visto_em, l.confirmado_em
             FROM usuarios u
             LEFT JOIN avisos_leituras l ON l.usuario_id = u.id AND l.aviso_id = ?
             WHERE u.id IN ({$marcadores})
             ORDER BY (l.confirmado_em IS NOT NULL), (l.visto_em IS NOT NULL), u.nome"
        );
        $stmt->execute([$avisoId, ...$usuarioIds]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validar(array $dados, array $destinatarios): ?string
    {
        if (trim($dados['titulo'] ?? '') === '') {
            return 'Informe o título do aviso.';
        }
        if (trim($dados['conteudo'] ?? '') === '') {
            return 'Informe o conteúdo do aviso.';
        }
        if (empty($destinatarios)) {
            return 'Selecione pelo menos um destinatário (Todos, um grupo ou um usuário).';
        }

        return null;
    }

    /** @param array<int, array{tipo:string, id?:int}> $destinatarios */
    private function salvarDestinatarios(int $avisoId, array $destinatarios): void
    {
        $this->pdo->beginTransaction();

        $this->pdo->prepare('DELETE FROM avisos_destinatarios WHERE aviso_id = ?')->execute([$avisoId]);

        $stmt = $this->pdo->prepare('INSERT INTO avisos_destinatarios (aviso_id, tipo, destinatario_id) VALUES (?, ?, ?)');
        foreach ($destinatarios as $d) {
            if (!in_array($d['tipo'], ['todos', 'grupo', 'usuario'], true)) {
                continue;
            }
            $stmt->execute([$avisoId, $d['tipo'], $d['tipo'] === 'todos' ? null : (int)($d['id'] ?? 0)]);
        }

        $this->pdo->commit();
    }
}
