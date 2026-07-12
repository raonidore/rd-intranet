<?php

namespace App\Services;

use App\Repositories\SpeedtestRepository;

class SpeedtestService
{
    private LinuxService $linux;
    private SpeedtestRepository $repo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new SpeedtestRepository();
    }

    public function instalado(): bool
    {
        $resultado = $this->linux->executar('command -v speedtest');

        return $resultado['success'];
    }

    public function instalar(): array
    {
        // adicionar repositorio + apt update + install pode demorar mais
        // que o max_execution_time padrao.
        set_time_limit(180);

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/speedtest_instalar_web.sh');
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function executar(): array
    {
        // o teste em si costuma levar de 15 a 40s.
        set_time_limit(90);

        $resultado = $this->linux->executar('/opt/rdtecnologia/scripts/speedtest_executar_web.sh');
        $bruto = json_decode(trim($resultado['output']), true);

        if (!is_array($bruto) || isset($bruto['success'])) {
            $mensagem = is_array($bruto) ? ($bruto['message'] ?? 'Erro desconhecido.') : $resultado['output'];
            $this->repo->criar(['status' => 'erro', 'mensagem_erro' => $mensagem]);

            return ['success' => false, 'message' => $mensagem];
        }

        if (!isset($bruto['download']['bandwidth'], $bruto['upload']['bandwidth'])) {
            $mensagem = 'Resposta inesperada do Speedtest CLI.';
            $this->repo->criar(['status' => 'erro', 'mensagem_erro' => $mensagem]);

            return ['success' => false, 'message' => $mensagem];
        }

        $downloadMbps = round(($bruto['download']['bandwidth'] * 8) / 1_000_000, 2);
        $uploadMbps = round(($bruto['upload']['bandwidth'] * 8) / 1_000_000, 2);
        $pingMs = round($bruto['ping']['latency'] ?? 0, 2);
        $jitterMs = round($bruto['ping']['jitter'] ?? 0, 2);
        $servidor = $bruto['server']['name'] ?? null;
        $isp = $bruto['isp'] ?? null;

        $this->repo->criar([
            'status' => 'concluido',
            'download_mbps' => $downloadMbps,
            'upload_mbps' => $uploadMbps,
            'ping_ms' => $pingMs,
            'jitter_ms' => $jitterMs,
            'servidor' => $servidor,
            'isp' => $isp,
        ]);

        return [
            'success' => true,
            'message' => "Teste concluído: {$downloadMbps} Mbps download, {$uploadMbps} Mbps upload, {$pingMs} ms de ping.",
        ];
    }

    public function ultimoConcluido(): ?array
    {
        return $this->repo->ultimoConcluido();
    }

    public function historico(int $limite = 20): array
    {
        return $this->repo->listar($limite);
    }
}
