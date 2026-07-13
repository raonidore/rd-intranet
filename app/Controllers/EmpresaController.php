<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;

class EmpresaController extends Controller
{
    private AtivoService $ativoService;

    public function __construct()
    {
        $this->ativoService = new AtivoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/empresa', [
            'nome' => $this->ativoService->nomeEmpresa(),
            'sigla' => $this->ativoService->siglaEmpresa(),
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkAdmin();

        $this->ativoService->salvarEmpresa(
            trim($_POST['nome'] ?? ''),
            trim($_POST['sigla'] ?? '')
        );

        header('Location: ' . url('/administracao/empresa'));
        exit;
    }
}
