<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChamadoCategoriaService;
use App\Services\ChamadoSetorService;
use App\Services\ChamadoSlaService;
use App\Services\ChamadoSubcategoriaService;
use App\Services\NotificationService;

class ChamadoCategoriaController extends Controller
{
    private ChamadoCategoriaService $service;
    private ChamadoSlaService $slaService;
    private ChamadoSubcategoriaService $subcategoriaService;

    public function __construct()
    {
        $this->service = new ChamadoCategoriaService();
        $this->slaService = new ChamadoSlaService();
        $this->subcategoriaService = new ChamadoSubcategoriaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $categorias = $this->service->listar();

        $slasPorCategoria = [];
        foreach ($categorias as $categoria) {
            $slasPorCategoria[$categoria['id']] = $this->slaService->listarPorCategoria((int)$categoria['id']);
        }

        $this->view('chamados/categorias', [
            'categorias' => $categorias,
            'setores' => (new ChamadoSetorService())->listarAtivos(),
            'slasPorCategoria' => $slasPorCategoria,
            'subcategoriasPorCategoria' => $this->subcategoriaService->listarAgrupadas(),
            'slasPorSubcategoria' => $this->subcategoriaService->listarSlasAgrupados(),
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $nome = trim($_POST['nome'] ?? '');
        $setorId = !empty($_POST['setor_padrao_id']) ? (int)$_POST['setor_padrao_id'] : null;
        $resultado = $this->service->criar($nome, $setorId, isset($_POST['usa_sla']));

        AuditService::registrar('Chamados', 'Criar categoria', "Categoria \"{$nome}\": {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $setorId = !empty($_POST['setor_padrao_id']) ? (int)$_POST['setor_padrao_id'] : null;
        $resultado = $this->service->atualizar($id, $nome, $setorId, isset($_POST['ativo']), isset($_POST['exige_subcategoria']), isset($_POST['usa_sla']));

        AuditService::registrar('Chamados', 'Atualizar categoria', "Categoria #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado, $id);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluir($id);

        AuditService::registrar('Chamados', 'Excluir categoria', "Categoria #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function salvarSla(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $resposta = (int)($_POST['tempo_primeira_resposta_min'] ?? 0);
        $resolucao = (int)($_POST['tempo_resolucao_min'] ?? 0);

        $resultado = $this->slaService->atualizar($id, $resposta, $resolucao);

        AuditService::registrar('Chamados', 'SLA', "SLA #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado, (int)($_POST['categoria_id'] ?? 0) ?: null);
    }

    public function criarSubcategoria(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $setorId = !empty($_POST['setor_padrao_id']) ? (int)$_POST['setor_padrao_id'] : null;
        $resultado = $this->subcategoriaService->criar($categoriaId, $nome, $setorId);

        AuditService::registrar('Chamados', 'Criar subcategoria', "Categoria #{$categoriaId}, subcategoria \"{$nome}\": {$resultado['message']}");

        $this->notificarEVoltar($resultado, $categoriaId);
    }

    public function atualizarSubcategoria(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $setorId = !empty($_POST['setor_padrao_id']) ? (int)$_POST['setor_padrao_id'] : null;
        $resultado = $this->subcategoriaService->atualizar(
            $id,
            trim($_POST['nome'] ?? ''),
            $setorId,
            ($_POST['sla_modo'] ?? 'herdar') === 'proprio',
            isset($_POST['ativo'])
        );

        AuditService::registrar('Chamados', 'Atualizar subcategoria', "Subcategoria #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado, $this->categoriaDaSubcategoria($id));
    }

    public function excluirSubcategoria(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $categoriaId = $this->categoriaDaSubcategoria($id);
        $resultado = $this->subcategoriaService->excluir($id);

        AuditService::registrar('Chamados', 'Excluir subcategoria', "Subcategoria #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado, $categoriaId);
    }

    public function salvarSlaSubcategoria(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->subcategoriaService->atualizarSla(
            $id,
            (int)($_POST['tempo_primeira_resposta_min'] ?? 0),
            (int)($_POST['tempo_resolucao_min'] ?? 0)
        );

        AuditService::registrar('Chamados', 'SLA subcategoria', "SLA de subcategoria #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado, (int)($_POST['categoria_id'] ?? 0) ?: null);
    }

    private function categoriaDaSubcategoria(int $subcategoriaId): ?int
    {
        $subcategoria = $this->subcategoriaService->buscar($subcategoriaId);

        return $subcategoria ? (int)$subcategoria['categoria_id'] : null;
    }

    /** $categoriaAberta: volta com o painel dessa categoria já expandido (senão o usuário perde o lugar a cada salvar). */
    private function notificarEVoltar(array $resultado, ?int $categoriaAberta = null): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/categorias' . ($categoriaAberta ? '?aberta=' . $categoriaAberta : '')));
        exit;
    }
}
