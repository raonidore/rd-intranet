<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;
use App\Services\SambaDominioService;

class SambaDominioController extends Controller
{
    private SambaDominioService $service;

    public function __construct()
    {
        $this->service = new SambaDominioService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');

        $status = $this->service->status();

        $this->view('samba/dominio/index', [
            'status' => $status,
            'checklist' => $status['is_dc'] ? null : $this->service->checklistPreRequisitos(),
        ]);
    }

    public function hostnameAplicar(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        header('Content-Type: application/json');

        $resultado = $this->service->aplicarHostname(trim($_POST['hostname'] ?? ''));

        if ($resultado['success']) {
            NotificationService::success($resultado['message']);
        }

        echo json_encode($resultado);
    }

    public function provisionar(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        header('Content-Type: application/json');

        $resultado = $this->service->provisionar(
            $_POST['realm'] ?? '',
            $_POST['workgroup'] ?? '',
            $_POST['senha'] ?? '',
            $_POST['confirmacao'] ?? ''
        );

        echo json_encode($resultado);
    }

    public function provisionarStatus(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        header('Content-Type: application/json');

        echo json_encode($this->service->statusProvisionamento((string)($_GET['id'] ?? '')));
    }

    // ── Fase 2: gestão básica do domínio ────────────────────────────────

    private function exigirDC(): bool
    {
        if (!$this->service->ehDC()) {
            NotificationService::error('Este servidor ainda não é um Controlador de Domínio.');
            header('Location: ' . url('/samba/dominio'));
            exit;
        }

        return true;
    }

    public function usuarios(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/usuarios', [
            'usuarios' => $this->service->listarUsuarios(),
        ]);
    }

    public function usuarioNovoForm(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/usuario_novo', []);
    }

    public function usuarioNovo(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->criarUsuario(
            trim($_POST['username'] ?? ''),
            trim($_POST['nome_completo'] ?? ''),
            $_POST['senha'] ?? '',
            $_POST['confirmacao'] ?? ''
        );

        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Usuário criado.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/usuarios'));
        exit;
    }

    public function usuarioSenhaForm(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/usuario_senha', [
            'username' => trim($_GET['username'] ?? ''),
        ]);
    }

    public function usuarioSenha(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->resetarSenha(
            trim($_POST['username'] ?? ''),
            $_POST['senha'] ?? '',
            $_POST['confirmacao'] ?? ''
        );

        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Senha redefinida.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/usuarios'));
        exit;
    }

    public function usuarioAtivar(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->ativarUsuario(trim($_GET['username'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Usuário ativado.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/usuarios'));
        exit;
    }

    public function usuarioDesativar(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->desativarUsuario(trim($_GET['username'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Usuário desativado.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/usuarios'));
        exit;
    }

    public function grupos(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/grupos', [
            'grupos' => $this->service->listarGrupos(),
        ]);
    }

    public function grupoNovoForm(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/grupo_novo', []);
    }

    public function grupoNovo(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->criarGrupo(trim($_POST['nome'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Grupo criado.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/grupos'));
        exit;
    }

    public function grupoMembros(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $nome = trim($_GET['nome'] ?? '');

        $this->view('samba/dominio/grupo_membros', [
            'nome' => $nome,
            'membros' => $this->service->listarMembrosGrupo($nome),
            'usuarios' => $this->service->listarUsuarios(),
        ]);
    }

    public function grupoMembroAdicionar(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $grupo = trim($_POST['grupo'] ?? '');

        $resultado = $this->service->adicionarMembroGrupo($grupo, trim($_POST['usuario'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Membro adicionado.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/grupos/membros?nome=' . urlencode($grupo)));
        exit;
    }

    public function computadores(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/computadores', [
            'computadores' => $this->service->listarComputadores(),
        ]);
    }
}
