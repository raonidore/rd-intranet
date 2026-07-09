<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\DependenciaService;

class DependenciaController extends Controller
{
    private DependenciaService $service;

    public function __construct()
    {
        $this->service = new DependenciaService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_dependencias');

        $this->view('infrastructure/dependencias', [
            'itens' => $this->service->checklist(),
        ]);
    }

    public function instalar(): void
    {
        AuthMiddleware::checkModulo('infra_dependencias');
        header('Content-Type: application/json');

        $chave = trim($_POST['chave'] ?? '');
        $resultado = $this->service->instalar($chave);

        AuditService::registrar('Dependências', 'Instalar', "Instalação de '{$chave}': " . ($resultado['success'] ? 'ok' : 'falhou'));

        echo json_encode($resultado);
    }
}
