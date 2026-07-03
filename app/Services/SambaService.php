<?php

namespace App\Services;

use App\Repositories\SambaUsuarioRepository;

class SambaService
{
    private SambaUsuarioRepository $repository;

    public function __construct()
    {
        $this->repository = new SambaUsuarioRepository();
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

    public function alterarSenha(int $id, string $senha, string $confirmacao): void
    {
        $usuario = $this->buscarUsuario($id);

        if (!$usuario) {
            NotificationService::error('Usuário Samba não encontrado.');
            return;
        }

        if ($senha === '' || $confirmacao === '') {
            NotificationService::error('Informe a nova senha e a confirmação.');
            return;
        }

        if ($senha !== $confirmacao) {
            NotificationService::error('A senha e a confirmação não conferem.');
            return;
        }

        $linux = new LinuxService();

        $resultado = $linux->executarScript(
            '/opt/rdtecnologia/scripts/altera_senha_samba_web.sh',
            [$usuario['login'], $senha]
        );

        if ($resultado['success']) {
            AuditService::registrar(
                'Samba',
                'Alteração de senha',
                'Senha Samba alterada para o usuário '.$usuario['login'].'.'
            );

            NotificationService::success(
                'Senha do usuário '.$usuario['login'].' alterada com sucesso.',
                $resultado['output']
            );
        } else {
            AuditService::registrar(
                'Samba',
                'Falha ao alterar senha',
                'Falha ao alterar senha Samba do usuário '.$usuario['login'].'.'
            );

            NotificationService::error(
                'Erro ao alterar senha do usuário '.$usuario['login'].'.',
                $resultado['output']
            );
        }
    }

    public function desativarUsuario(int $id): void
    {
        $usuario = $this->buscarUsuario($id);

        if (!$usuario) {
            NotificationService::error('Usuário Samba não encontrado.');
            return;
        }

        if ($usuario['login'] === 'ti') {
            NotificationService::error('O usuário administrativo principal não pode ser desativado.');
            return;
        }

        $linux = new LinuxService();

        $resultado = $linux->executarScript(
            '/opt/rdtecnologia/scripts/desativa_usuario_samba_web.sh',
            [$usuario['login']]
        );

        if ($resultado['success']) {
            $this->repository->atualizarStatus($id, 'desativado');

            AuditService::registrar(
                'Samba',
                'Desativação de usuário',
                'Usuário '.$usuario['login'].' foi desativado.'
            );

            NotificationService::success(
                'Usuário '.$usuario['login'].' desativado com sucesso.',
                $resultado['output']
            );
        } else {
            AuditService::registrar(
                'Samba',
                'Falha ao desativar usuário',
                'Falha ao desativar o usuário '.$usuario['login'].'.'
            );

            NotificationService::error(
                'Erro ao desativar o usuário '.$usuario['login'].'.',
                $resultado['output']
            );
        }
    }
}
