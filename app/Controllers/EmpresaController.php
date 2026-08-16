<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtivoService;
use App\Services\NotificationService;

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
            'logoConfigurada' => $this->ativoService->logoEmpresaConfigurada(),
            'logoSistemaConfigurada' => $this->ativoService->logoSistemaConfigurada(),
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

    public function logoUpload(): void
    {
        AuthMiddleware::checkAdmin();

        $arquivo = $_FILES['logo'] ?? null;

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Falha no upload do arquivo.');
        } else {
            $this->ativoService->salvarLogoEmpresa($arquivo['tmp_name'], $arquivo['name']);
        }

        header('Location: ' . url('/administracao/empresa'));
        exit;
    }

    public function logoRemover(): void
    {
        AuthMiddleware::checkAdmin();

        $this->ativoService->removerLogoEmpresa();

        header('Location: ' . url('/administracao/empresa'));
        exit;
    }

    /** Servida no <img> do menu lateral -- exige só login (não admin), já que o menu aparece pra qualquer usuário. */
    public function logo(): void
    {
        AuthMiddleware::check();

        if (!$this->ativoService->logoEmpresaConfigurada()) {
            http_response_code(404);
            return;
        }

        $caminho = AtivoService::caminhoLogoEmpresa();
        header('Content-Type: ' . $this->ativoService->logoEmpresaMime());
        header('Content-Length: ' . filesize($caminho));
        header('Cache-Control: private, max-age=300');
        readfile($caminho);
    }

    public function logoSistemaUpload(): void
    {
        AuthMiddleware::checkAdmin();

        $arquivo = $_FILES['logo'] ?? null;

        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            NotificationService::error('Falha no upload do arquivo.');
        } else {
            $this->ativoService->salvarLogoSistema($arquivo['tmp_name'], $arquivo['name']);
        }

        header('Location: ' . url('/administracao/empresa'));
        exit;
    }

    public function logoSistemaRemover(): void
    {
        AuthMiddleware::checkAdmin();

        $this->ativoService->removerLogoSistema();

        header('Location: ' . url('/administracao/empresa'));
        exit;
    }

    /** Servida no <img> do topo do menu lateral -- mesma lógica de acesso da logo da empresa. */
    public function logoSistema(): void
    {
        AuthMiddleware::check();

        if (!$this->ativoService->logoSistemaConfigurada()) {
            http_response_code(404);
            return;
        }

        $caminho = AtivoService::caminhoLogoSistema();
        header('Content-Type: ' . $this->ativoService->logoSistemaMime());
        header('Content-Length: ' . filesize($caminho));
        header('Cache-Control: private, max-age=300');
        readfile($caminho);
    }
}
