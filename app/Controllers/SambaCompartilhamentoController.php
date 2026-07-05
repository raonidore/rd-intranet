<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\SambaCompartilhamentoService;
use App\Services\SambaGrupoService;

class SambaCompartilhamentoController extends Controller
{
    private SambaCompartilhamentoService $service;
    private SambaGrupoService $grupoService;

    public function __construct()
    {
        $this->service = new SambaCompartilhamentoService();
        $this->grupoService = new SambaGrupoService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

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
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $this->view('samba/compartilhamento_novo', [
            'grupos' => $this->grupoService->listarNomes(),
        ]);
    }

    public function novo(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $grupo = strtolower(trim($_POST['grupo'] ?? ''));

        $pasta = preg_replace('/[^A-Za-z0-9_-]/', '', $nome);

        $dados = [
            'nome' => $nome,
            'descricao' => $descricao,
            'grupo' => $grupo,
            'caminho' => '/srv/samba/Compartilhamentos/' . $pasta,
            'somente_leitura' => isset($_POST['somente_leitura']) ? 1 : 0,
            'lixeira' => isset($_POST['lixeira']) ? 1 : 0,
            'bloqueio_extensoes' => isset($_POST['bloqueio_extensoes']) ? 1 : 0,
        ];

        $this->service->criar($dados);

        header('Location: ' . url('/samba/compartilhamentos'));
        exit;
    }

    /*
     |---------------------------------------------------------
     | Editar
     |---------------------------------------------------------
     */

    public function editarForm(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $id = (int)($_GET['id'] ?? 0);

        $compartilhamento = $this->service->buscar($id);

        if (!$compartilhamento) {
            header('Location: ' . url('/samba/compartilhamentos'));
            exit;
        }

        $this->view('samba/compartilhamento_editar', [
            'compartilhamento' => $compartilhamento,
            'grupos' => $this->grupoService->listarNomes(),
        ]);
    }

    public function editar(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $id = (int)($_POST['id'] ?? 0);

        $dados = [
            'nome' => trim($_POST['nome']),
            'descricao' => trim($_POST['descricao']),
            'grupo' => trim($_POST['grupo']),
            'caminho' => trim($_POST['caminho']),
        ];

        $this->service->editar($id, $dados);

        header('Location: ' . url('/samba/compartilhamentos'));
        exit;
    }

    /*
     |---------------------------------------------------------
     | Usuários autorizados
     |---------------------------------------------------------
     */

    public function usuariosForm(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $id = (int)($_GET['id'] ?? 0);

        $compartilhamento = $this->service->buscar($id);

        if (!$compartilhamento) {
            header('Location: ' . url('/samba/compartilhamentos'));
            exit;
        }

        $this->view('samba/compartilhamento_usuarios', [
            'compartilhamento' => $compartilhamento,
            'usuarios' => $this->service->usuariosDisponiveis(),
            'autorizados' => $this->service->usuariosAutorizados($id)
        ]);
    }

    public function usuariosSalvar(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $id = (int)($_POST['id'] ?? 0);

        $this->service->salvarUsuarios($id, $_POST);

        header('Location: ' . url('/samba/compartilhamentos'));
        exit;
    }

    /*
     |---------------------------------------------------------
     | Segurança
     |---------------------------------------------------------
     */

    public function segurancaForm(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $id = (int)($_GET['id'] ?? 0);

        $compartilhamento = $this->service->buscar($id);

        if (!$compartilhamento) {
            header('Location: ' . url('/samba/compartilhamentos'));
            exit;
        }

        $this->view('samba/compartilhamento_seguranca', [
            'compartilhamento' => $compartilhamento
        ]);
    }

    public function segurancaSalvar(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $id = (int)($_POST['id'] ?? 0);

        $dados = [
            'somente_leitura' => isset($_POST['somente_leitura']) ? 1 : 0,
            'lixeira' => isset($_POST['lixeira']) ? 1 : 0,
            'bloqueio_extensoes' => isset($_POST['bloqueio_extensoes']) ? 1 : 0,
        ];

        $this->service->atualizarSeguranca($id, $dados);

        header('Location: ' . url('/samba/compartilhamentos'));
        exit;
    }

    /*
     |---------------------------------------------------------
     | Excluir
     |---------------------------------------------------------
     */

    public function excluirForm(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $id = (int)($_GET['id'] ?? 0);

        $compartilhamento = $this->service->buscar($id);

        if (!$compartilhamento) {
            header('Location: ' . url('/samba/compartilhamentos'));
            exit;
        }

        $this->view('samba/compartilhamento_excluir', [
            'compartilhamento' => $compartilhamento
        ]);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('samba_compartilhamentos');

        $id = (int)($_POST['id'] ?? 0);

        $this->service->excluir($id);

        header('Location: ' . url('/samba/compartilhamentos'));
        exit;
    }
}
