<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CronService;
use App\Services\NotificationService;

class CronController extends Controller
{
    private CronService $service;

    public function __construct()
    {
        $this->service = new CronService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $this->view('infrastructure/cron', [
            'jobs' => $this->service->listar(),
            'sync' => $this->service->statusSincronizacao(),
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $this->view('infrastructure/cron_form', ['job' => null]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $nome = trim($_POST['nome'] ?? '');
        $dados = $this->dadosDoPost();

        $resultado = $this->service->criar($dados);

        if ($resultado['success']) {
            AuditService::registrar('Cron', 'Criar job', "Job \"{$nome}\" criado.");
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/infraestrutura/cron'));
        exit;
    }

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $id = (int)($_GET['id'] ?? 0);
        $job = $this->service->buscar($id);

        if (!$job) {
            header('Location: ' . url('/infraestrutura/cron'));
            exit;
        }

        $this->view('infrastructure/cron_form', ['job' => $job]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $id = (int)($_POST['id'] ?? 0);
        $dados = $this->dadosDoPost();

        $resultado = $this->service->atualizar($id, $dados);

        if ($resultado['success']) {
            AuditService::registrar('Cron', 'Editar job', "Job #{$id} atualizado.");
            NotificationService::success($resultado['message']);
        } else {
            NotificationService::error($resultado['message']);
        }

        header('Location: ' . url('/infraestrutura/cron'));
        exit;
    }

    public function excluirForm(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $id = (int)($_GET['id'] ?? 0);
        $job = $this->service->buscar($id);

        if (!$job) {
            header('Location: ' . url('/infraestrutura/cron'));
            exit;
        }

        $this->view('infrastructure/cron_excluir', ['job' => $job]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $id = (int)($_POST['id'] ?? 0);
        $job = $this->service->buscar($id);

        $resultado = $this->service->excluir($id);

        AuditService::registrar('Cron', 'Excluir job', "Job #{$id} (\"" . ($job['nome'] ?? '?') . "\") excluído.");
        NotificationService::success($resultado['message']);

        header('Location: ' . url('/infraestrutura/cron'));
        exit;
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $id = (int)($_GET['id'] ?? 0);
        $resultado = $this->service->alternarAtivo($id, true);

        AuditService::registrar('Cron', 'Ativar job', "Job #{$id} ativado.");
        NotificationService::success($resultado['message']);

        header('Location: ' . url('/infraestrutura/cron'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $id = (int)($_GET['id'] ?? 0);
        $resultado = $this->service->alternarAtivo($id, false);

        AuditService::registrar('Cron', 'Desativar job', "Job #{$id} desativado.");
        NotificationService::success($resultado['message']);

        header('Location: ' . url('/infraestrutura/cron'));
        exit;
    }

    public function executarAgora(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->executarAgora($id);

        AuditService::registrar('Cron', 'Executar agora', "Execução manual do job #{$id}: " . ($resultado['success'] ? 'ok' : 'falhou'));

        echo json_encode($resultado);
    }

    public function logs(): void
    {
        AuthMiddleware::checkModulo('infra_cron');

        $id = (int)($_GET['id'] ?? 0);
        $job = $this->service->buscar($id);

        if (!$job) {
            header('Location: ' . url('/infraestrutura/cron'));
            exit;
        }

        $this->view('infrastructure/cron_logs', [
            'job' => $job,
            'logAgendado' => $this->service->logAgendado($id),
        ]);
    }

    private function dadosDoPost(): array
    {
        return [
            'nome' => trim($_POST['nome'] ?? ''),
            'descricao' => trim($_POST['descricao'] ?? ''),
            'expressao' => trim($_POST['expressao'] ?? ''),
            'usuario_execucao' => trim($_POST['usuario_execucao'] ?? ''),
            'comando' => trim($_POST['comando'] ?? ''),
            'ativo' => isset($_POST['ativo']),
        ];
    }
}
