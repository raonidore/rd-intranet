<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaLixeiraService;

class SambaLixeiraController extends Controller
{
    private SambaLixeiraService $service;

    public function __construct()
    {
        $this->service = new SambaLixeiraService();
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $this->view('samba/lixeira', [
            'itens' => $this->service->listar()
        ]);
    }

    public function restaurar(): void
    {
        AuthMiddleware::check();

        $this->service->restaurar($_POST['nome'] ?? '');

        header('Location: ' . url('/samba/lixeira'));
        exit;
    }

    public function excluir(): void
    {
        AuthMiddleware::check();

        $this->service->excluirDefinitivo($_POST['nome'] ?? '');

        header('Location: ' . url('/samba/lixeira'));
        exit;
    }
}
