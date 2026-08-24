<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Resolve quem pode ver/editar/excluir uma CATEGORIA de documentos.
 * Concessão é por usuário direto OU por grupo (grupos.php, genérico --
 * ver GrupoService). Sem NENHUMA concessão numa categoria, só admin
 * enxerga -- fail closed, mesmo princípio de samba_compartilhamento_portal_usuarios.
 * Quando há mais de uma concessão aplicável (ex: usuário direto E via
 * grupo), o resultado é o OR de todas -- a mais permissiva vale.
 */
class DocumentoPermissaoService
{
    private PDO $pdo;
    private GrupoService $grupoService;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->grupoService = new GrupoService();
    }

    /** @return array{visualizar: bool, editar: bool, excluir: bool} */
    public function efetiva(int $categoriaId, int $usuarioId, bool $ehAdmin): array
    {
        if ($ehAdmin) {
            return ['visualizar' => true, 'editar' => true, 'excluir' => true];
        }

        $grupoIds = $this->grupoService->idsGruposDoUsuario($usuarioId);

        $sql = "SELECT pode_visualizar, pode_editar, pode_excluir FROM documentos_permissoes
                WHERE categoria_id = ? AND (
                    (sujeito_tipo = 'usuario' AND sujeito_id = ?)";
        $params = [$categoriaId, $usuarioId];

        if ($grupoIds) {
            $marcadores = implode(',', array_fill(0, count($grupoIds), '?'));
            $sql .= " OR (sujeito_tipo = 'grupo' AND sujeito_id IN ({$marcadores}))";
            $params = array_merge($params, $grupoIds);
        }

        $sql .= ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $resultado = ['visualizar' => false, 'editar' => false, 'excluir' => false];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $resultado['visualizar'] = $resultado['visualizar'] || (bool)$linha['pode_visualizar'];
            $resultado['editar'] = $resultado['editar'] || (bool)$linha['pode_editar'];
            $resultado['excluir'] = $resultado['excluir'] || (bool)$linha['pode_excluir'];
        }

        return $resultado;
    }

    public function podeVisualizar(int $categoriaId, int $usuarioId, bool $ehAdmin): bool
    {
        return $this->efetiva($categoriaId, $usuarioId, $ehAdmin)['visualizar'];
    }

    public function podeEditar(int $categoriaId, int $usuarioId, bool $ehAdmin): bool
    {
        return $this->efetiva($categoriaId, $usuarioId, $ehAdmin)['editar'];
    }

    public function podeExcluir(int $categoriaId, int $usuarioId, bool $ehAdmin): bool
    {
        return $this->efetiva($categoriaId, $usuarioId, $ehAdmin)['excluir'];
    }

    /** @return int[] ids de categoria que este usuário pode ao menos visualizar. */
    public function categoriasVisiveis(int $usuarioId, bool $ehAdmin): array
    {
        if ($ehAdmin) {
            return array_map('intval', $this->pdo->query('SELECT id FROM documentos_categorias')->fetchAll(PDO::FETCH_COLUMN));
        }

        $grupoIds = $this->grupoService->idsGruposDoUsuario($usuarioId);

        $sql = "SELECT DISTINCT categoria_id FROM documentos_permissoes
                WHERE pode_visualizar = 1 AND (
                    (sujeito_tipo = 'usuario' AND sujeito_id = ?)";
        $params = [$usuarioId];

        if ($grupoIds) {
            $marcadores = implode(',', array_fill(0, count($grupoIds), '?'));
            $sql .= " OR (sujeito_tipo = 'grupo' AND sujeito_id IN ({$marcadores}))";
            $params = array_merge($params, $grupoIds);
        }

        $sql .= ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int, array{sujeito_tipo:string, sujeito_id:int, pode_visualizar:bool, pode_editar:bool, pode_excluir:bool}> */
    public function listarDaCategoria(int $categoriaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documentos_permissoes WHERE categoria_id = ?');
        $stmt->execute([$categoriaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Substitui todas as concessões de uma categoria.
     * @param array<int, array{sujeito_tipo:string, sujeito_id:int, pode_visualizar:bool, pode_editar:bool, pode_excluir:bool}> $concessoes
     */
    public function salvarDaCategoria(int $categoriaId, array $concessoes): void
    {
        $this->pdo->beginTransaction();

        $this->pdo->prepare('DELETE FROM documentos_permissoes WHERE categoria_id = ?')->execute([$categoriaId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO documentos_permissoes (categoria_id, sujeito_tipo, sujeito_id, pode_visualizar, pode_editar, pode_excluir)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($concessoes as $c) {
            if (!in_array($c['sujeito_tipo'], ['usuario', 'grupo'], true)) {
                continue;
            }
            if (empty($c['pode_visualizar']) && empty($c['pode_editar']) && empty($c['pode_excluir'])) {
                continue;
            }

            $stmt->execute([
                $categoriaId,
                $c['sujeito_tipo'],
                (int)$c['sujeito_id'],
                !empty($c['pode_visualizar']) ? 1 : 0,
                !empty($c['pode_editar']) ? 1 : 0,
                !empty($c['pode_excluir']) ? 1 : 0,
            ]);
        }

        $this->pdo->commit();
    }
}
