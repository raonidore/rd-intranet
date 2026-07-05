<?php

namespace App\Services;

use App\Repositories\SambaCompartilhamentoRepository;
use App\Repositories\SambaUsuarioRepository;

class SambaCompartilhamentoService
{
    private SambaCompartilhamentoRepository $repository;
    private SambaUsuarioRepository $usuarioRepository;
    private LinuxService $linux;

    public function __construct()
    {
        $this->repository = new SambaCompartilhamentoRepository();
        $this->usuarioRepository = new SambaUsuarioRepository();
        $this->linux = new LinuxService();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    public function dashboard(): array
    {
        return [
            'total' => $this->repository->contarTotal(),
            'ativos' => $this->repository->contarAtivos(),
            'lixeira' => $this->repository->contarComLixeira(),
            'bloqueio_extensoes' => $this->repository->contarComBloqueioExtensoes(),
        ];
    }

    public function buscar(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }

    public function usuariosDisponiveis(): array
    {
        return $this->usuarioRepository->listar();
    }

    public function usuariosAutorizados(int $id): array
    {
        return $this->repository->usuariosAutorizados($id);
    }

    public function criar(array $dados): bool
    {
        if ($this->repository->buscarPorNome($dados['nome'])) {
            NotificationService::error('Já existe um compartilhamento com este nome.');
            return false;
        }

        $resultadoLinux = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/cria_compartilhamento_samba_web.sh',
            [
                $dados['nome'],
                $dados['caminho'],
                $dados['grupo']
            ]
        );

        if (!$resultadoLinux['success']) {
            NotificationService::error('Erro ao criar a estrutura Linux.', $resultadoLinux['output']);
            return false;
        }

        $this->repository->criar($dados);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Compartilhamento',
            $dados['nome'],
            'Novo compartilhamento criado: '.$dados['nome'].' em '.$dados['caminho'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Criar Compartilhamento',
            'Compartilhamento '.$dados['nome'].' criado e aguardando deploy.'
        );

        NotificationService::success(
            'Compartilhamento criado com sucesso. A configuração ainda precisa ser aplicada no Deploy Center.',
            $resultadoLinux['output']
        );

        return true;
    }

    public function editar(int $id, array $dados): bool
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return false;
        }

        if ($dados['grupo'] !== $compartilhamento['grupo']) {
            $resultadoLinux = $this->linux->executarScript(
                '/opt/rdtecnologia/scripts/altera_grupo_compartilhamento_web.sh',
                [$compartilhamento['caminho'], $dados['grupo']]
            );

            if (!$resultadoLinux['success']) {
                NotificationService::error('Erro ao alterar o grupo no sistema.', $resultadoLinux['output']);
                return false;
            }
        }

        $this->repository->atualizar($id, $dados);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Compartilhamento',
            $dados['nome'],
            'Compartilhamento atualizado: '.$dados['nome'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Editar Compartilhamento',
            'Compartilhamento '.$compartilhamento['nome'].' atualizado.'
        );

        NotificationService::success('Compartilhamento atualizado. Alterações pendentes para deploy.');

        return true;
    }

    public function atualizarSeguranca(int $id, array $dados): bool
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return false;
        }

        $this->repository->atualizarSeguranca($id, $dados);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Segurança',
            $compartilhamento['nome'],
            'Políticas de segurança atualizadas para '.$compartilhamento['nome'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Segurança Compartilhamento',
            'Segurança do compartilhamento '.$compartilhamento['nome'].' atualizada.'
        );

        NotificationService::success('Segurança atualizada. Alterações pendentes para deploy.');

        return true;
    }

    public function salvarUsuarios(int $id, array $post): bool
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return false;
        }

        $usuarios = [];
        $tokensAcl = [];

        foreach (($post['usuarios'] ?? []) as $usuarioId => $permissoes) {
            $leitura = isset($permissoes['leitura']) ? 1 : 0;
            $escrita = isset($permissoes['escrita']) ? 1 : 0;

            $usuarios[] = [
                'usuario_id' => (int)$usuarioId,
                'leitura' => $leitura,
                'escrita' => $escrita,
            ];

            if ($leitura || $escrita) {
                $usuarioSamba = $this->usuarioRepository->buscarPorId((int)$usuarioId);

                if ($usuarioSamba) {
                    $tokensAcl[] = "{$usuarioSamba['login']}:{$leitura}:{$escrita}";
                }
            }
        }

        $resultadoAcl = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/aplicar_acl_compartilhamento_web.sh',
            array_merge([$compartilhamento['nome'], $compartilhamento['grupo']], $tokensAcl)
        );

        if (!$resultadoAcl['success']) {
            NotificationService::error('Erro ao aplicar permissões no sistema.', $resultadoAcl['output']);
            return false;
        }

        $this->repository->salvarUsuariosAutorizados($id, $usuarios);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Usuários',
            $compartilhamento['nome'],
            'Usuários autorizados atualizados para '.$compartilhamento['nome'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Usuários Compartilhamento',
            'Usuários do compartilhamento '.$compartilhamento['nome'].' atualizados.'
        );

        NotificationService::success('Usuários autorizados atualizados. Alterações pendentes para deploy.');

        return true;
    }

    public function excluir(int $id): bool
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return false;
        }

        $this->repository->excluir($id);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Compartilhamento',
            $compartilhamento['nome'],
            'Compartilhamento removido: '.$compartilhamento['nome'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Excluir Compartilhamento',
            'Compartilhamento '.$compartilhamento['nome'].' removido do cadastro.'
        );

        NotificationService::success('Compartilhamento removido do cadastro. Alterações pendentes para deploy.');

        return true;
    }
}
