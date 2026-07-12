<?php

namespace App\Services;

use App\Core\Vpn\IpsecConfigWriter;

class IpsecConfigDeployService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * @param array<string,string> $caCerts nome_arquivo => conteudo PEM
     * @return array{success: bool, message: string}
     */
    public function deploy(string $conf, string $secrets, array $caCerts = []): array
    {
        $writer = new IpsecConfigWriter();

        try {
            $writer->writeTemp($conf, $secrets, $caCerts);
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_ikev2_aplicar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}
