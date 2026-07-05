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

        $gerado = $generator->generate($shares);
        $tempFile = $writer->writeTemp($gerado['conteudo']);

        $validacao = $validator->validateFile('/etc/samba/smb.conf');

        if (!$validacao['success']) {
            return [
                'success' => false,
                'output' => $validacao['output']
            ];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/apply_shares_conf_web.sh',
            [$tempFile]
        );

        if (!empty($gerado['ignorados'])) {
            $resultado['output'] = "Compartilhamentos ignorados (grupo inexistente no sistema):\n"
                . implode("\n", $gerado['ignorados'])
                . "\n\n" . $resultado['output'];
        }

        return $resultado;
    }
}
