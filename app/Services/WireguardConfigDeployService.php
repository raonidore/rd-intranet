<?php

namespace App\Services;

use App\Core\Vpn\WireguardConfigWriter;

class WireguardConfigDeployService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deploy(string $conteudo, string $interface): array
    {
        $writer = new WireguardConfigWriter();

        try {
            $tempFile = $writer->writeTemp($conteudo);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/vpn_wireguard_aplicar_web.sh',
            [$tempFile, $interface]
        );

        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}
