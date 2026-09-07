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
            $_POST['confirmacao'] ?? '',
            trim($_POST['email'] ?? ''),
            trim($_POST['telefone'] ?? ''),
            trim($_POST['descricao'] ?? '')
        );

        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Usuário criado.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/usuarios'));
        exit;
    }

    public function usuarioVer(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $username = trim($_GET['username'] ?? '');

        $this->view('samba/dominio/usuario_ver', [
            'username' => $username,
            'detalhes' => $this->service->detalhesUsuario($username),
        ]);
    }

    public function usuarioExcluir(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->excluirUsuario(trim($_GET['username'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Usuário excluído.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/usuarios'));
        exit;
    }

    public function usuarioDesbloquear(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->desbloquearUsuario(trim($_GET['username'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Usuário desbloqueado.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/usuarios'));
        exit;
    }

    public function usuarioExpiracaoForm(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/usuario_expiracao', [
            'username' => trim($_GET['username'] ?? ''),
        ]);
    }

    public function usuarioExpiracao(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $username = trim($_POST['username'] ?? '');
        $nunca = ($_POST['nunca'] ?? '') === '1';
        $dias = $nunca ? null : (int)($_POST['dias'] ?? 0);

        $resultado = $this->service->definirExpiracaoSenha($username, $dias);
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Expiração atualizada.') : NotificationService::error($resultado['message']);
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

    public function grupoMembroRemover(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $grupo = trim($_GET['grupo'] ?? '');

        $resultado = $this->service->removerMembroGrupo($grupo, trim($_GET['usuario'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Membro removido.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/grupos/membros?nome=' . urlencode($grupo)));
        exit;
    }

    public function grupoExcluir(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->excluirGrupo(trim($_GET['nome'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Grupo excluído.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/grupos'));
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

    public function computadorExcluir(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->excluirComputador(trim($_GET['nome'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Computador removido.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/computadores'));
        exit;
    }

    // ── Política de senha do domínio ────────────────────────────────────

    public function politicaSenhaForm(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/politica_senha', [
            'politica' => $this->service->obterPoliticaSenha(),
        ]);
    }

    public function politicaSenhaSalvar(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->salvarPoliticaSenha([
            'complexidade' => ($_POST['complexidade'] ?? '') === '1',
            'historico' => $_POST['historico'] ?? '',
            'tamanho_minimo' => $_POST['tamanho_minimo'] ?? '',
            'idade_minima_dias' => $_POST['idade_minima_dias'] ?? '',
            'idade_maxima_dias' => $_POST['idade_maxima_dias'] ?? '',
            'bloqueio_limite_tentativas' => $_POST['bloqueio_limite_tentativas'] ?? '',
            'bloqueio_duracao_min' => $_POST['bloqueio_duracao_min'] ?? '',
            'bloqueio_reset_min' => $_POST['bloqueio_reset_min'] ?? '',
        ]);

        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'Política atualizada.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/politica-senha'));
        exit;
    }

    // ── Unidades Organizacionais ─────────────────────────────────────────

    public function ous(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/ous', [
            'ous' => $this->service->listarOus(),
        ]);
    }

    public function ouNovoForm(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/ou_novo', []);
    }

    public function ouNovo(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->criarOu(trim($_POST['nome'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'OU criada.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/ous'));
        exit;
    }

    public function ouExcluir(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->excluirOu(trim($_GET['dn'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'OU excluída.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/ous'));
        exit;
    }

    // ── GPOs (mecânica: criar/excluir/vincular -- ver comentário no service) ──

    public function gpos(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $status = $this->service->status();
        $realm = trim((string)($status['realm'] ?? ''));
        $dominioDn = $realm !== '' ? 'DC=' . implode(',DC=', explode('.', $realm)) : '';

        $this->view('samba/dominio/gpos', [
            'gpos' => $this->service->listarGpos(),
            'ous' => $this->service->listarOus(),
            'dominioDn' => $dominioDn,
            'aclcheck' => $this->service->verificarAclSysvol(),
        ]);
    }

    public function gpoNovoForm(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $this->view('samba/dominio/gpo_novo', []);
    }

    public function gpoNovo(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->criarGpo(trim($_POST['nome'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'GPO criada.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/gpos'));
        exit;
    }

    public function gpoExcluir(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->excluirGpo(trim($_GET['guid'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'GPO excluída.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/gpos'));
        exit;
    }

    public function gpoVincular(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->vincularGpo(trim($_POST['guid'] ?? ''), trim($_POST['container_dn'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'GPO vinculada.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/gpos'));
        exit;
    }

    public function gpoDesvincular(): void
    {
        AuthMiddleware::checkModulo('samba_dominio');
        $this->exigirDC();

        $resultado = $this->service->desvincularGpo(trim($_GET['guid'] ?? ''), trim($_GET['container_dn'] ?? ''));
        $resultado['success'] ? NotificationService::success($resultado['message'] ?? 'GPO desvinculada.') : NotificationService::error($resultado['message']);
        header('Location: ' . url('/samba/dominio/gpos'));
        exit;
    }
}
