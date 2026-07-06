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

    /**
     * Converte a saída crua do traceroute -A (uma linha de texto por salto)
     * em uma lista estruturada [ttl, host, ip, as, ms, timeout] pra tabela.
     */
    public function parsearTraceroute(string $output): array
    {
        $saltos = [];

        foreach (explode("\n", $output) as $linha) {
            $linha = rtrim($linha);

            if ($linha === '' || !preg_match('/^\s*\d+\s/', $linha)) {
                continue;
            }

            if (preg_match('/^\s*(\d+)\s+\*\s*$/', $linha, $m)) {
                $saltos[] = [
                    'ttl' => (int)$m[1],
                    'host' => null,
                    'ip' => null,
                    'as' => null,
                    'ms' => null,
                    'timeout' => true,
                ];
                continue;
            }

            if (preg_match('/^\s*(\d+)\s+(\S+)\s+\(([^)]+)\)\s+\[([^\]]*)\]\s+([\d.]+)\s*ms/', $linha, $m)) {
                $as = $m[4] === '*' || $m[4] === '' ? null : $m[4];

                $saltos[] = [
                    'ttl' => (int)$m[1],
                    'host' => $m[2] === $m[3] ? null : $m[2],
                    'ip' => $m[3],
                    'as' => $as,
                    'ms' => (float)$m[5],
                    'timeout' => false,
                ];
                continue;
            }

            // Linha reconhecida (começa com numero) mas em formato
            // inesperado (ex: sem -A funcionando) -- guarda mesmo assim.
            if (preg_match('/^\s*(\d+)\s+(.*)$/', $linha, $m)) {
                $saltos[] = [
                    'ttl' => (int)$m[1],
                    'host' => null,
                    'ip' => null,
                    'as' => null,
                    'ms' => null,
                    'timeout' => false,
                    'bruto' => trim($m[2]),
                ];
            }
        }

        return $saltos;
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
