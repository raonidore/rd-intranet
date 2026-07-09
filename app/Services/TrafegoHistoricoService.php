<?php

namespace App\Services;

use App\Repositories\RedeTrafegoRepository;

class TrafegoHistoricoService
{
    private RedeTrafegoRepository $repo;

    public function __construct()
    {
        $this->repo = new RedeTrafegoRepository();
    }

    /**
     * Roda via cron (scripts/system/coletar_trafego.php): tira uma foto dos
     * contadores acumulados de cada interface (menos loopback) e guarda.
     */
    public function coletarAmostra(): void
    {
        $interfaces = (new ServerInfoService())->snapshot()['rede']['interfaces'];

        foreach ($interfaces as $i) {
            if ($i['nome'] === 'lo') {
                continue;
            }

            $this->repo->registrarAmostra(
                $i['nome'],
                $i['rx_bytes'],
                $i['tx_bytes'],
                $i['rx_packets'],
                $i['tx_packets']
            );
        }
    }

    public function consumoDiario(int $dias = 30): array
    {
        $linhas = $this->repo->consumoPorDia($dias);

        $porDia = [];

        foreach ($linhas as $l) {
            $rx = max(0, (int)$l['rx_max'] - (int)$l['rx_min']);
            $tx = max(0, (int)$l['tx_max'] - (int)$l['tx_min']);

            $porDia[$l['dia']] ??= ['dia' => $l['dia'], 'download' => 0, 'upload' => 0, 'interfaces' => []];
            $porDia[$l['dia']]['download'] += $rx;
            $porDia[$l['dia']]['upload'] += $tx;
            $porDia[$l['dia']]['interfaces'][] = [
                'nome' => $l['interface'],
                'download' => $rx,
                'upload' => $tx,
                'rx_packets' => max(0, (int)$l['rx_packets_max'] - (int)$l['rx_packets_min']),
                'tx_packets' => max(0, (int)$l['tx_packets_max'] - (int)$l['tx_packets_min']),
            ];
        }

        return array_values($porDia);
    }
}
