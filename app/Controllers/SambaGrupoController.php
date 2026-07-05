<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaGrupoService;

class SambaGrupoController extends Controller
{
    private SambaGrupoService $service;

    public function __construct()
    {
        $this->service = new SambaGrupoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('samba_grupos');

        $this->view('samba/grupos', [
            'grupos' => $this->service->listarComDetalhes(),
        ]);
    }

    public function renomearForm(): void
    {
        AuthMiddleware::checkModulo('samba_grupos');

        $nome = trim($_GET['nome'] ?? '');
        $grupo = null;

        foreach ($this->service->listarComDetalhes() as $g) {
            if ($g['nome'] === $nome) {
                $grupo = $g;
                break;
            }
        }

        if (!$grupo) {
            header('Location: ' . url('/samba/grupos'));
            exit;
        }

        $this->view('samba/grupo_renomear', [
            'grupo' => $grupo,
        ]);
    }

    public function renomear(): void
    {
        AuthMiddleware::checkModulo('samba_grupos');

        $this->service->renomear(
            $_POST['antigo'] ?? '',
            $_POST['novo'] ?? ''
        );

        header('Location: ' . url('/samba/grupos'));
        exit;
    }
}
