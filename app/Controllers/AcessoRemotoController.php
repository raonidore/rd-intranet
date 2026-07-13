<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AcessoRemotoService;

class AcessoRemotoController extends Controller
{
    private AcessoRemotoService $service;

    public function __construct()
    {
        $this->service = new AcessoRemotoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');

        $this->view('ativos/acesso_remoto', [
            'instalado' => $this->service->instalado(),
            'rodando' => $this->service->rodando(),
            'porta' => $this->service->porta(),
            'urlConsole' => $this->service->urlConsole(),
        ]);
    }

    public function instalar(): void
    {
        AuthMiddleware::checkModulo('ativos_acesso_remoto');
        header('Content-Type: application/json');

        echo json_encode($this->service->instalar());
    }
}
