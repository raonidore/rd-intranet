<?php

namespace App\Services;

use App\Repositories\SambaUsuarioRepository;

class SambaService
{
    private SambaUsuarioRepository $repository;
    private LinuxService $linux;

    public function __construct()
    {
        $this->repository = new SambaUsuarioRepository();
        $this->linux = new LinuxService();
    }

    public function listarUsuarios(): array
    {
        return $this->repository->listar();
    }

    public function dashboard(): array
    {
        return [
            'total' => $this->repository->contarTotal(),
            'ativos' => $this->repository->contarAtivos(),
            'ssh' => $this->repository->contarComSSH(),
            'compartilhamentos' => 3
        ];
    }

    public function buscarUsuario(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }

    public function departamentos(): array
    {
        return $this->repository->departamentos();
    }

    public function alterarSenha(int $id, string $senha, string $confirmacao): void
    {
        $usuario = $this->buscarUsuario($id);

        if (!$usuario) {
            NotificationService::error("Usuário não encontrado.");
            return;
        }

        if ($senha !== $confirmacao) {
            NotificationService::error("As senhas não conferem.");
            return;
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/altera_senha_samba_web.sh',
            [$usuario['login'], $senha]
        );

        if ($resultado['success']) {
            AuditService::registrar('Samba', 'Alteração de senha', 'Senha alterada para '.$usuario['login']);
            NotificationService::success('Senha alterada com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao alterar senha.', $resultado['output']);
        }
    }

    public function desativarUsuario(int $id): void
    {
        $usuario = $this->buscarUsuario($id);

        if (!$usuario) {
            NotificationService::error('Usuário não encontrado.');
            return;
        }

        if ($usuario['login'] === 'ti') {
            NotificationService::error('O usuário administrativo principal não pode ser desativado.');
            return;
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/desativa_usuario_samba_web.sh',
            [$usuario['login']]
        );

        if ($resultado['success']) {
            $this->repository->atualizarStatus($id, 'desativado');
            AuditService::registrar('Samba', 'Desativação', 'Usuário '.$usuario['login'].' desativado.');
            NotificationService::success('Usuário desativado com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao desativar usuário.', $resultado['output']);
        }
    }

    public function ativarUsuario(int $id): void
    {
        $usuario = $this->buscarUsuario($id);

        if (!$usuario) {
            NotificationService::error('Usuário não encontrado.');
            return;
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/ativa_usuario_samba_web.sh',
            [$usuario['login']]
        );

        if ($resultado['success']) {
            $this->repository->atualizarStatus($id, 'ativo');
            AuditService::registrar('Samba', 'Ativação', 'Usuário '.$usuario['login'].' ativado.');
            NotificationService::success('Usuário ativado com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao ativar usuário.', $resultado['output']);
        }
    }

    public function editarUsuario(int $id, string $nome, string $departamento, bool $ssh): void
    {
        $usuario = $this->buscarUsuario($id);

        if (!$usuario) {
            NotificationService::error('Usuário não encontrado.');
            return;
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/edita_usuario_samba_web.sh',
            [$usuario['login'], $nome, $departamento, $ssh ? 'sim' : 'nao']
        );

        if ($resultado['success']) {
            $this->repository->atualizar($id, $nome, $departamento, $ssh);
            AuditService::registrar('Samba', 'Edição', 'Usuário '.$usuario['login'].' atualizado.');
            NotificationService::success('Usuário atualizado com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao atualizar usuário.', $resultado['output']);
        }
    }

    public function excluirUsuario(int $id): void
    {
        $usuario = $this->buscarUsuario($id);

        if (!$usuario) {
            NotificationService::error('Usuário não encontrado.');
            return;
        }

        if ($usuario['login'] === 'ti') {
            NotificationService::error('O usuário administrativo principal não pode ser excluído.');
            return;
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/exclui_usuario_samba_web.sh',
            [$usuario['login']]
        );

        if ($resultado['success']) {
            $this->repository->excluir($id);
            AuditService::registrar('Samba', 'Exclusão', 'Usuário '.$usuario['login'].' removido.');
            NotificationService::success('Usuário excluído com sucesso.', $resultado['output']);
        } else {
            NotificationService::error('Erro ao excluir usuário.', $resultado['output']);
        }
    }
}
