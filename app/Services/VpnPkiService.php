<?php

namespace App\Services;

/**
 * Wrapper fino sobre o script root vpn_openvpn_pki_web.sh (easy-rsa).
 * Compartilhado pelo OpenVPN (Fase 2) -- pensado desde já pra IKEv2
 * (Fase 3) poder reaproveitar os métodos de emissão/revogação, já que
 * os dois são PKIs X.509. IKEv2 vai precisar de Extended Key Usage
 * (serverAuth) e identidade por SAN que o OpenVPN não usa -- não
 * implementado ainda, mas os métodos abaixo já retornam o conteúdo cru
 * dos certificados, então a Fase 3 pode montar isso sem precisar mudar
 * a assinatura destes métodos.
 */
class VpnPkiService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function inicializar(): array
    {
        set_time_limit(180);

        return $this->chamar(['init']);
    }

    /**
     * @return array{success: bool, message?: string, ca?: string, cert?: string, key?: string, tls_crypt?: string}
     */
    public function emitirCliente(string $nome): array
    {
        set_time_limit(60);

        return $this->chamar(['emitir_cliente', $nome]);
    }

    public function baixarCliente(string $nome): array
    {
        return $this->chamar(['baixar_cliente', $nome]);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function revogarCliente(string $nome): array
    {
        set_time_limit(30);

        return $this->chamar(['revogar_cliente', $nome]);
    }

    private function chamar(array $parametros): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_openvpn_pki_web.sh', $parametros);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}
