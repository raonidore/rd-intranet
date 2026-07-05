<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\ApacheSiteService;

class ApacheSiteController extends Controller
{
    private ApacheSiteService $service;

    public function __construct()
    {
        $this->service = new ApacheSiteService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('apache_sites');

        $this->view('apache/sites', [
            'sites' => $this->service->listar(),
        ]);
    }

    public function ver(): void
    {
        AuthMiddleware::checkModulo('apache_sites');

        $nome = trim($_GET['nome'] ?? '');
        $arquivoConteudo = $this->service->ver($nome);

        if ($arquivoConteudo === null) {
            header('Location: ' . url('/apache/sites'));
            exit;
        }

        $this->view('apache/site_ver', [
            'nome' => $nome,
            'arquivoConteudo' => $arquivoConteudo,
        ]);
    }

    public function habilitar(): void
    {
        AuthMiddleware::checkModulo('apache_sites');

        $this->service->habilitar(trim($_POST['nome'] ?? ''));

        header('Location: ' . url('/apache/sites'));
        exit;
    }

    public function desabilitar(): void
    {
        AuthMiddleware::checkModulo('apache_sites');

        $this->service->desabilitar(trim($_POST['nome'] ?? ''));

        header('Location: ' . url('/apache/sites'));
        exit;
    }
}
