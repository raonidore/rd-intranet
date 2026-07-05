<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ApacheModuloService;

class ApacheModuloController extends Controller
{
    private ApacheModuloService $service;

    public function __construct()
    {
        $this->service = new ApacheModuloService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('apache_modulos');

        $this->view('apache/modulos', [
            'modulos' => $this->service->listar(),
        ]);
    }

    public function habilitar(): void
    {
        AuthMiddleware::checkModulo('apache_modulos');

        $this->service->habilitar(trim($_POST['nome'] ?? ''));

        header('Location: ' . url('/apache/modulos'));
        exit;
    }

    public function desabilitar(): void
    {
        AuthMiddleware::checkModulo('apache_modulos');

        $this->service->desabilitar(trim($_POST['nome'] ?? ''));

        header('Location: ' . url('/apache/modulos'));
        exit;
    }
}
