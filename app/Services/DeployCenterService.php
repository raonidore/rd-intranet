<?php

namespace App\Services;

use App\Repositories\DeployCenterRepository;

class DeployCenterService
{
    private DeployCenterRepository $repository;

    public function __construct()
    {
        $this->repository = new DeployCenterRepository();
    }

    public function status(string $modulo): ?array
    {
        return $this->repository->status($modulo);
    }

    public function pendencias(string $modulo): array
    {
        return $this->repository->pendencias($modulo);
    }

    public function marcarPendente(
        string $modulo,
        string $tipo = 'Alteração',
        ?string $referencia = null,
        string $descricao = 'Alteração pendente.'
    ): void {
        $this->repository->marcarPendente($modulo);

        $this->repository->registrarPendencia(
            $modulo,
            $tipo,
            $referencia,
            $descricao
        );
    }

    public function aplicarSamba(): void
    {
        $deploy = new SambaConfigDeployService();
        $resultado = $deploy->deploy();

        if ($resultado['success']) {
            $backup = null;

            if (preg_match('/Backup criado em:\s*(.+)/', $resultado['output'], $m)) {
                $backup = trim($m[1]);
            }

            $this->repository->marcarAplicado('samba', $backup);

            AuditService::registrar(
                'Deploy',
                'Aplicar Samba',
                'Configuração Samba aplicada com sucesso.'
            );

            NotificationService::success(
                'Configuração Samba aplicada com sucesso.',
                $resultado['output']
            );

            return;
        }

        NotificationService::error(
            'Erro ao aplicar configuração Samba.',
            $resultado['output']
        );
    }
}
