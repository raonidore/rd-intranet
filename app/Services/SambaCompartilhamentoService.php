<?php

namespace App\Services;

use App\Repositories\SambaCompartilhamentoRepository;

class SambaCompartilhamentoService
{
    private SambaCompartilhamentoRepository $repository;
    private LinuxService $linux;

    public function __construct()
    {
        $this->repository = new SambaCompartilhamentoRepository();
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

    public function criar(array $dados): bool
    {
        if ($this->repository->buscarPorNome($dados['nome'])) {
            NotificationService::error('Já existe um compartilhamento com este nome.');
            return false;
        }

        $resultadoLinux = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/cria_compartilhamento_samba_web.sh',
            [$dados['nome'], $dados['caminho'], $dados['grupo']]
        );

        if (!$resultadoLinux['success']) {
            NotificationService::error('Erro ao criar a estrutura Linux.', $resultadoLinux['output']);
            return false;
        }

        $this->repository->criar($dados);

        $deploy = new SambaConfigDeployService();
        $resultadoDeploy = $deploy->deploy();

        if (!$resultadoDeploy['success']) {
            NotificationService::error('Compartilhamento criado, mas houve erro no deploy do Samba.', $resultadoDeploy['output']);
            return false;
        }

        AuditService::registrar(
            'Samba',
            'Criar Compartilhamento',
            'Compartilhamento '.$dados['nome'].' criado e aplicado ao smb.conf.'
        );

        NotificationService::success(
            'Compartilhamento criado e aplicado ao Samba com sucesso.',
            $resultadoLinux['output']."\n\n".$resultadoDeploy['output']
        );

        return true;
    }
}
