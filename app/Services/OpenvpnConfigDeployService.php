<?php

namespace App\Services;

use App\Core\Vpn\OpenvpnConfigWriter;

class OpenvpnConfigDeployService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deploy(string $conteudo): array
    {
        $writer = new OpenvpnConfigWriter();

        try {
            $tempFile = $writer->writeTemp($conteudo);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/vpn_openvpn_aplicar_web.sh',
            [$tempFile]
        );

        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}
