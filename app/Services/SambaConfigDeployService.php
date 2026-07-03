<?php

namespace App\Services;

use App\Core\Samba\SambaConfigGenerator;
use App\Core\Samba\SambaConfigWriter;
use App\Core\Samba\SambaValidator;
use App\Repositories\SambaCompartilhamentoRepository;

class SambaConfigDeployService
{
    private SambaCompartilhamentoRepository $repository;
    private LinuxService $linux;

    public function __construct()
    {
        $this->repository = new SambaCompartilhamentoRepository();
        $this->linux = new LinuxService();
    }

    public function deploy(): array
    {
        $shares = $this->repository->listar();

        $generator = new SambaConfigGenerator();
        $writer = new SambaConfigWriter();
        $validator = new SambaValidator();

        $config = $generator->generate($shares);
        $tempFile = $writer->writeTemp($config);

        $validacao = $validator->validateFile($tempFile);

        if (!$validacao['success']) {
            return [
                'success' => false,
                'output' => $validacao['output']
            ];
        }

        return $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/apply_smb_conf_web.sh',
            [$tempFile]
        );
    }
}
