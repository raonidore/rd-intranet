<?php

namespace App\Services;

class NetworkConfigService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function interfacesValidas(): array
    {
        $saida  = shell_exec('ip -o link show 2>/dev/null') ?? '';
        $nomes  = [];

        foreach (explode("\n", trim($saida)) as $linha) {
            if (!preg_match('/^\d+:\s+(\S+?):/', $linha, $m)) continue;
            if ($m[1] === 'lo') continue;
            $nomes[] = $m[1];
        }

        return $nomes;
    }

    public function configuracaoAtual(string $interface): array
    {
        foreach ((new ServerInfoService())->snapshot()['rede']['interfaces'] as $i) {
            if ($i['nome'] === $interface) return $i;
        }

        return ['nome' => $interface, 'ipv4' => [], 'ipv6' => [], 'estado' => '-', 'mac' => '-'];
    }

    public function aplicar(string $interface, string $modo, string $ipCidr, string $gateway, string $dnsCsv): array
    {
        if (!in_array($interface, $this->interfacesValidas(), true)) {
            return ['success' => false, 'message' => 'Interface inválida.'];
        }

        if ($modo === 'estatico') {
            if (!$this->validarCidr($ipCidr)) {
                return ['success' => false, 'message' => 'Endereço IP/CIDR inválido. Use o formato 192.168.1.10/24.'];
            }
            if (!$this->validarIp($gateway)) {
                return ['success' => false, 'message' => 'Gateway inválido.'];
            }
            foreach (array_filter(array_map('trim', explode(',', $dnsCsv))) as $dns) {
                if (!$this->validarIp($dns)) {
                    return ['success' => false, 'message' => "DNS inválido: {$dns}"];
                }
            }
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/network_aplicar_web.sh',
            [$interface, $modo, $ipCidr ?: '-', $gateway ?: '-', $dnsCsv ?: '-']
        );

        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => 'Resposta inesperada do script: ' . $resultado['output']];
    }

    public function confirmar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/network_confirmar_web.sh');
        $dados     = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => 'Resposta inesperada do script: ' . $resultado['output']];
    }

    /**
     * Forca uma renovacao de concessao DHCP so nessa interface
     * (networkctl renew), sem reescrever netplan nem mexer nas demais
     * interfaces -- uso tipico: reserva de IP nova criada no switch/DHCP
     * pro MAC do servidor, sem precisar esperar o proximo ciclo natural
     * de renovacao nem reiniciar o servidor.
     */
    public function renovar(string $interface): array
    {
        if (!in_array($interface, $this->interfacesValidas(), true)) {
            return ['success' => false, 'message' => 'Interface inválida.'];
        }

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/network_renovar_web.sh',
            [$interface]
        );

        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => 'Resposta inesperada do script: ' . $resultado['output']];
    }

    public function statusRollback(): array
    {
        $resultado = $this->linux->executar(
            "systemctl is-active rd-netplan-rollback.timer 2>/dev/null"
        );

        if (trim($resultado['output']) !== 'active') {
            return ['pendente' => false, 'segundos_restantes' => 0];
        }

        // --on-active do systemd-run cria timer monotônico: as propriedades
        // NextElapseUSec* não dão um timestamp absoluto confiável de forma
        // simples, então o prazo é gravado à parte por network_aplicar_web.sh.
        $deadline = @file_get_contents('/etc/rd-intranet/.rede-deadline');
        $restante = $deadline !== false ? max(0, (int)trim($deadline) - time()) : 0;

        return ['pendente' => $restante > 0, 'segundos_restantes' => $restante];
    }

    private function validarCidr(string $valor): bool
    {
        if (!preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/(\d{1,2})$#', $valor, $m)) {
            return false;
        }
        for ($i = 1; $i <= 4; $i++) {
            if ((int)$m[$i] > 255) return false;
        }
        return (int)$m[5] <= 32;
    }

    private function validarIp(string $valor): bool
    {
        if (!preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $valor, $m)) {
            return false;
        }
        for ($i = 1; $i <= 4; $i++) {
            if ((int)$m[$i] > 255) return false;
        }
        return true;
    }
}
