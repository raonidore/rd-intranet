<?php

namespace App\Services;

/**
 * Wrapper fino sobre o script root vpn_openvpn_pki_web.sh (easy-rsa).
 * Compartilhado pelo OpenVPN e pelo IKEv2 -- mesma CA, uma PKI só (nao
 * duas autoridades separadas pra confiar). O IKEv2 usa
 * emitirServidorComCn() pra ter um certificado de servidor com CN
 * igual ao endereço público (em vez do CN genérico "server" do
 * OpenVPN) -- é o que a maioria dos clientes IKEv2 nativos
 * (iOS/Android/Windows) valida ao conectar. Simplificação deliberada:
 * sem extensão SAN/Extended-Key-Usage dedicada -- CN batendo com o
 * endereço já cobre a validação da grande maioria dos clientes reais.
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
     * @return array{success: bool, message?: string, ca?: string, cert?: string, key?: string}
     */
    public function emitirServidorComCn(string $cn): array
    {
        set_time_limit(60);

        return $this->chamar(['emitir_servidor_cn', $cn]);
    }

    /**
     * @return array{success: bool, message?: string, ca?: string}
     */
    public function baixarCa(): array
    {
        return $this->chamar(['baixar_ca']);
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
