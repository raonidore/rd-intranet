<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Resolve quem pode ver/editar/excluir um COFRE de equipe. Concessão por
 * usuário direto OU por grupo (GrupoService). Sem NENHUMA concessão num
 * cofre = ninguém enxerga -- fail closed, SEM bypass de admin (diferente
 * de DocumentoPermissaoService::efetiva()): Cofre de Senhas já é módulo
 * restrito (ModuloCatalogo::MODULOS_RESTRITOS), então a resolução de
 * permissão dentro dele também precisa negar por padrão, senão um admin
 * com acesso só a "seguranca_cofre_senhas_gerenciar" (pra administrar a
 * ESTRUTURA de cofres) passaria a enxergar o CONTEÚDO de todo cofre de
 * equipe -- quebra o "acesso mínimo necessário" que motivou o módulo ser
 * restrito desde o início.
 */
class CofrePermissaoService
{
    private PDO $pdo;
    private GrupoService $grupoService;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->grupoService = new GrupoService();
    }

    /** @return array{visualizar: bool, editar: bool, excluir: bool} */
    public function efetiva(int $cofreId, int $usuarioId): array
    {
        $grupoIds = $this->grupoService->idsGruposDoUsuario($usuarioId);

        $sql = "SELECT pode_visualizar, pode_editar, pode_excluir FROM cofre_permissoes
                WHERE cofre_id = ? AND (
                    (sujeito_tipo = 'usuario' AND sujeito_id = ?)";
        $params = [$cofreId, $usuarioId];

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

    public function podeVisualizar(int $cofreId, int $usuarioId): bool
    {
        return $this->efetiva($cofreId, $usuarioId)['visualizar'];
    }

    public function podeEditar(int $cofreId, int $usuarioId): bool
    {
        return $this->efetiva($cofreId, $usuarioId)['editar'];
    }

    public function podeExcluir(int $cofreId, int $usuarioId): bool
    {
        return $this->efetiva($cofreId, $usuarioId)['excluir'];
    }

    /** @return int[] ids de cofre que o usuário pode ao menos visualizar. */
    public function cofresVisiveis(int $usuarioId): array
    {
        $grupoIds = $this->grupoService->idsGruposDoUsuario($usuarioId);

        $sql = "SELECT DISTINCT cofre_id FROM cofre_permissoes
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

    /**
     * @return int[] ids de cofre onde o usuário pode editar -- usado pro
     * seletor "salvar em qual cofre" no formulário de novo item; só faz
     * sentido oferecer cofres onde ele efetivamente pode criar/editar itens.
     */
    public function cofresQueUsuarioPodeEditar(int $usuarioId): array
    {
        $visiveis = $this->cofresVisiveis($usuarioId);

        return array_values(array_filter($visiveis, fn($id) => $this->podeEditar($id, $usuarioId)));
    }

    /** @return array<int, array{sujeito_tipo:string, sujeito_id:int, pode_visualizar:bool, pode_editar:bool, pode_excluir:bool}> */
    public function listarDoCofre(int $cofreId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cofre_permissoes WHERE cofre_id = ?');
        $stmt->execute([$cofreId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Substitui todas as concessões de um cofre (DELETE + INSERT em transação).
     * @param array<int, array{sujeito_tipo:string, sujeito_id:int, pode_visualizar:bool, pode_editar:bool, pode_excluir:bool}> $concessoes
     */
    public function salvarDoCofre(int $cofreId, array $concessoes): void
    {
        $this->pdo->beginTransaction();

        $this->pdo->prepare('DELETE FROM cofre_permissoes WHERE cofre_id = ?')->execute([$cofreId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO cofre_permissoes (cofre_id, sujeito_tipo, sujeito_id, pode_visualizar, pode_editar, pode_excluir)
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
                $cofreId,
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
