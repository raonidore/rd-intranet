<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\CronService;
use App\Services\SpeedtestService;

class SpeedtestController extends Controller
{
    private const NOME_JOB_CRON = 'Teste de velocidade agendado';

    private SpeedtestService $service;

    public function __construct()
    {
        $this->service = new SpeedtestService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('infra_speedtest');

        $this->view('infrastructure/speedtest', [
            'instalado' => $this->service->instalado(),
            'ultimo' => $this->service->ultimoConcluido(),
            'historico' => $this->service->historico(),
            'periodicoAtivo' => $this->periodicoAtivo(),
        ]);
    }

    public function instalar(): void
    {
        AuthMiddleware::checkModulo('infra_speedtest');
        header('Content-Type: application/json');

        set_time_limit(180);

        $resultado = $this->service->instalar();

        AuditService::registrar('Teste de Velocidade', 'Instalar', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function testar(): void
    {
        AuthMiddleware::checkModulo('infra_speedtest');
        header('Content-Type: application/json');

        set_time_limit(90);

        $resultado = $this->service->executar();

        AuditService::registrar('Teste de Velocidade', 'Testar agora', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function ativarPeriodico(): void
    {
        AuthMiddleware::checkModulo('infra_speedtest');
        header('Content-Type: application/json');

        if ($this->periodicoAtivo()) {
            echo json_encode(['success' => true, 'message' => 'Teste diário já estava ativo.']);
            return;
        }

        $resultado = (new CronService())->criar([
            'nome' => self::NOME_JOB_CRON,
            'descricao' => 'Roda o teste de velocidade uma vez por dia e guarda no histórico (Infraestrutura > Teste de Velocidade).',
            'expressao' => '@daily',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd speedtest:executar',
            'ativo' => true,
        ]);

        AuditService::registrar('Teste de Velocidade', 'Ativar teste diário', $resultado['message']);

        echo json_encode($resultado);
    }

    private function periodicoAtivo(): bool
    {
        foreach ((new CronService())->listar() as $job) {
            if ($job['nome'] === self::NOME_JOB_CRON) {
                return true;
            }
        }

        return false;
    }
}
