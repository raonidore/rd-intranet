<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;
use App\Services\SambaAuditoriaService;

class SambaAuditoriaController extends Controller
{
    private SambaAuditoriaService $service;

    public function __construct()
    {
        $this->service = new SambaAuditoriaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('samba_auditoria');

        $ativa = $this->service->ativa();

        $filtros = [
            'usuario' => trim($_GET['usuario'] ?? ''),
            'compartilhamento' => trim($_GET['compartilhamento'] ?? ''),
            'acao' => trim($_GET['acao'] ?? ''),
            'busca' => trim($_GET['busca'] ?? ''),
        ];

        $this->view('samba/auditoria', [
            'ativa' => $ativa,
            'filtros' => $filtros,
            'registros' => $ativa ? $this->service->listar($filtros) : [],
            'compartilhamentos' => $ativa ? $this->service->compartilhamentosNoLog() : [],
        ]);
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('samba_auditoria');

        $resultado = $this->service->ativar();
        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/samba/auditoria'));
        exit;
    }

    public function desativar(): void
    {
        AuthMiddleware::checkModulo('samba_auditoria');

        $resultado = $this->service->desativar();
        $resultado['success'] ? NotificationService::success($resultado['message']) : NotificationService::error($resultado['message']);

        header('Location: ' . url('/samba/auditoria'));
        exit;
    }
}
