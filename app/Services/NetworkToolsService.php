<?php

namespace App\Services;

class NetworkToolsService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function arp(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/arp_listar_web.sh');

        $linhas = [];

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);

            if ($linha === '') {
                continue;
            }

            if (preg_match('/^(\S+)\s+dev\s+(\S+)(?:\s+lladdr\s+(\S+))?\s+(\S+)$/', $linha, $m)) {
                $linhas[] = [
                    'ip' => $m[1],
                    'dev' => $m[2],
                    'mac' => $m[3] ?? '-',
                    'estado' => $m[4],
                ];
            } else {
                $linhas[] = ['ip' => $linha, 'dev' => '-', 'mac' => '-', 'estado' => '-'];
            }
        }

        return $linhas;
    }

    /**
     * Valida hostname (RFC 1123) ou IPv4/IPv6 literal. Chamado antes de
     * qualquer script que toque ping/traceroute -- nunca confia só na
     * validação do bash do lado de lá.
     */
    public function validarDestino(string $destino): bool
    {
        if ($destino === '' || strlen($destino) > 253) {
            return false;
        }

        if (filter_var($destino, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return (bool)preg_match(
            '/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/',
            $destino
        );
    }

    public function ping(string $destino): array
    {
        if (!$this->validarDestino($destino)) {
            return ['success' => false, 'output' => 'Destino inválido.'];
        }

        return $this->linux->executarScript('/opt/rdtecnologia/scripts/ping_web.sh', [$destino]);
    }

    public function traceroute(string $destino): array
    {
        if (!$this->validarDestino($destino)) {
            return ['success' => false, 'output' => 'Destino inválido.'];
        }

        return $this->linux->executarScript('/opt/rdtecnologia/scripts/traceroute_web.sh', [$destino]);
    }

    public function trafegoInterfaces(): array
    {
        $interfaces = (new ServerInfoService())->snapshot()['rede']['interfaces'];

        return array_map(function (array $i) {
            return [
                'nome' => $i['nome'],
                'rx_bytes' => $i['rx_bytes'],
                'tx_bytes' => $i['tx_bytes'],
            ];
        }, $interfaces);
    }
}
