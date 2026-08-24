<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\ChamadoCategoriaService;
use App\Services\ChamadoSetorService;
use App\Services\ChamadoSlaService;
use App\Services\NotificationService;

class ChamadoCategoriaController extends Controller
{
    private ChamadoCategoriaService $service;
    private ChamadoSlaService $slaService;

    public function __construct()
    {
        $this->service = new ChamadoCategoriaService();
        $this->slaService = new ChamadoSlaService();
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
        ]);
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $nome = trim($_POST['nome'] ?? '');
        $setorId = !empty($_POST['setor_padrao_id']) ? (int)$_POST['setor_padrao_id'] : null;
        $resultado = $this->service->criar($nome, $setorId);

        AuditService::registrar('Chamados', 'Criar categoria', "Categoria \"{$nome}\": {$resultado['message']}");

        $this->notificarEVoltar($resultado);
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('chamados_categorias');

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $setorId = !empty($_POST['setor_padrao_id']) ? (int)$_POST['setor_padrao_id'] : null;
        $resultado = $this->service->atualizar($id, $nome, $setorId, isset($_POST['ativo']));

        AuditService::registrar('Chamados', 'Atualizar categoria', "Categoria #{$id}: {$resultado['message']}");

        $this->notificarEVoltar($resultado);
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

        $this->notificarEVoltar($resultado);
    }

    private function notificarEVoltar(array $resultado): void
    {
        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/chamados/categorias'));
        exit;
    }
}
