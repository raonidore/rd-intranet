<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Services\ProjetoColunaService;
use App\Services\ProjetoService;

/** Colunas do quadro do projeto: criar, renomear/mudar situação, mover e excluir. Mesmo acesso das fases (quem enxerga o projeto). */
class ProjetoColunaController extends Controller
{
    private ProjetoColunaService $service;
    private ProjetoService $projetoService;

    public function __construct()
    {
        $this->service = new ProjetoColunaService();
        $this->projetoService = new ProjetoService();
    }

    private function garantirAcesso(int $projetoId): void
    {
        $projeto = $this->projetoService->buscar($projetoId);
        $usuarioId = (int)$_SESSION['usuario']['id'];
        $ehAdmin = PermissionService::ehAdmin() || PermissionService::temAcesso('projetos_gerenciar');

        if (!$projeto || !$this->projetoService->ehVisivelPara($projeto, $usuarioId, $ehAdmin)) {
            NotificationService::error('Você não tem acesso a esse projeto.');
            header('Location: ' . url('/projetos'));
            exit;
        }
    }

    /** Coluna informada no POST, já conferida contra o projeto e o acesso. */
    private function colunaOuSair(): array
    {
        $coluna = $this->service->buscar((int)($_POST['id'] ?? 0));
        if (!$coluna) {
            NotificationService::error('Coluna não encontrada.');
            header('Location: ' . url('/projetos'));
            exit;
        }
        $this->garantirAcesso((int)$coluna['projeto_id']);

        return $coluna;
    }

    private function voltar(int $projetoId, array $resultado): void
    {
        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);
        header('Location: ' . url('/projetos/ver?id=' . $projetoId));
        exit;
    }

    public function criar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $projetoId = (int)($_POST['projeto_id'] ?? 0);
        $this->garantirAcesso($projetoId);

        $this->voltar($projetoId, $this->service->criar($projetoId, (string)($_POST['nome'] ?? ''), (string)($_POST['situacao'] ?? ''), (int)$_SESSION['usuario']['id']));
    }

    public function atualizar(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $coluna = $this->colunaOuSair();
        $this->voltar((int)$coluna['projeto_id'], $this->service->atualizar((int)$coluna['id'], (string)($_POST['nome'] ?? ''), (string)($_POST['situacao'] ?? ''), (int)$_SESSION['usuario']['id']));
    }

    public function mover(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $coluna = $this->colunaOuSair();
        $this->voltar((int)$coluna['projeto_id'], $this->service->mover((int)$coluna['id'], (int)($_POST['direcao'] ?? 0)));
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('projetos_atendimentos');

        $coluna = $this->colunaOuSair();
        $this->voltar((int)$coluna['projeto_id'], $this->service->excluir((int)$coluna['id'], (int)($_POST['destino_id'] ?? 0), (int)$_SESSION['usuario']['id']));
    }
}
