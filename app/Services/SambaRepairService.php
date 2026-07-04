<?php

namespace App\Services;

use App\Repositories\SambaCompartilhamentoRepository;

class SambaRepairService
{
    private SambaCompartilhamentoRepository $repository;
    private LinuxService $linux;

    public function __construct()
    {
        $this->repository = new SambaCompartilhamentoRepository();
        $this->linux = new LinuxService();
    }

    public function importarPasta(string $nome, string $grupo, string $caminho): void
    {
        if ($this->repository->buscarPorNome($nome)) {
            NotificationService::error('Já existe um compartilhamento com este nome no banco.');
            return;
        }

        $this->repository->criar([
            'nome' => $nome,
            'descricao' => 'Compartilhamento importado pelo Diagnóstico Samba.',
            'caminho' => $caminho,
            'grupo' => $grupo,
            'somente_leitura' => 0,
            'lixeira' => 1,
            'bloqueio_extensoes' => 1,
        ]);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Importação',
            $nome,
            'Pasta órfã importada para a RD Intranet: '.$caminho.'.'
        );

        AuditService::registrar(
            'Samba',
            'Importar pasta órfã',
            'Pasta '.$nome.' importada para o banco.'
        );

        NotificationService::success(
            'Pasta importada com sucesso. Aplique a configuração na Central de Configurações.'
        );
    }

    public function moverPastaOrfaParaLixeira(string $nome): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/move_pasta_orfa_samba_web.sh',
            [$nome]
        );

        if ($resultado['success']) {
            AuditService::registrar(
                'Samba',
                'Mover pasta órfã',
                'Pasta órfã '.$nome.' movida para lixeira administrativa.'
            );

            NotificationService::success(
                'Pasta órfã movida para lixeira administrativa.',
                $resultado['output']
            );

            return;
        }

        NotificationService::error(
            'Erro ao mover pasta órfã.',
            $resultado['output']
        );
    }
}
