<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaCompartilhamentoService;

class SambaCompartilhamentoController extends Controller
{
    private SambaCompartilhamentoService $service;

    public function __construct()
    {
        $this->service = new SambaCompartilhamentoService();
    }

    public function index(): void
    {
        AuthMiddleware::check();

        $compartilhamentos = $this->service->listar();
        $dashboard = $this->service->dashboard();

        $this->view('samba/compartilhamentos', [
            'compartilhamentos' => $compartilhamentos,
            'total' => $dashboard['total'],
            'ativos' => $dashboard['ativos'],
            'lixeira' => $dashboard['lixeira'],
            'bloqueioExtensoes' => $dashboard['bloqueio_extensoes'],
        ]);
    }

    public function novoForm(): void
    {
        AuthMiddleware::check();

        $this->view('samba/compartilhamento_novo');
    }

    public function novo(): void
    {
        AuthMiddleware::check();

        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $grupo = strtolower(trim($_POST['grupo'] ?? ''));
        $caminho = trim($_POST['caminho'] ?? '');

        $dados = [
            'nome' => $nome,
            'descricao' => $descricao,
            'grupo' => $grupo,
            'caminho' => $caminho,
            'somente_leitura' => isset($_POST['somente_leitura']) ? 1 : 0,
            'lixeira' => isset($_POST['lixeira']) ? 1 : 0,
            'bloqueio_extensoes' => isset($_POST['bloqueio_extensoes']) ? 1 : 0,
        ];

        $this->service->criar($dados);

        header('Location: ' . url('/samba/compartilhamentos'));
        exit;
    }
}
