<?php

namespace App\Services;

use App\Repositories\DeployCenterRepository;

class SambaInventoryService
{
    private SambaDiscoveryService $discovery;
    private SambaAclService $acl;
    private SambaDiagnosticoService $diagnostico;
    private DeployCenterRepository $deployRepository;

    public function __construct()
    {
        $this->discovery = new SambaDiscoveryService();
        $this->acl = new SambaAclService();
        $this->diagnostico = new SambaDiagnosticoService();
        $this->deployRepository = new DeployCenterRepository();
    }

    public function snapshot(): array
    {
        $diagnostico = $this->diagnostico->executar();

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'services' => $diagnostico['servicos'],
            'shares' => $diagnostico['comparacao'],
            'folders' => $diagnostico['pastas'],
            'logs' => $diagnostico['logs'],
            'sessions' => $diagnostico['smbstatus'],
            'acl' => $this->acl->listar(),
            'deploy_pending' => $this->deployRepository->listarPendentes(),
        ];
    }
}
