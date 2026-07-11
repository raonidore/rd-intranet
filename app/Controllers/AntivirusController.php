<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AntivirusService;
use App\Services\AuditService;
use App\Services\CronService;

class AntivirusController extends Controller
{
    private const NOME_JOB_CRON = 'Verificação antivírus agendada';

    private AntivirusService $service;

    public function __construct()
    {
        $this->service = new AntivirusService();
    }

    public function index(): void
    {
        AuthMiddleware::checkModulo('seguranca_antivirus');

        $this->view('seguranca/antivirus', [
            'status' => $this->service->status(),
            'caminhoPadrao' => AntivirusService::caminhoPadrao(),
            'historico' => $this->service->historico(),
            'quarentena' => $this->service->quarentena(),
            'verificacaoPeriodicaAtiva' => $this->verificacaoPeriodicaAtiva(),
        ]);
    }

    public function instalar(): void
    {
        AuthMiddleware::checkModulo('seguranca_antivirus');
        header('Content-Type: application/json');

        set_time_limit(180);

        $resultado = $this->service->instalar();

        AuditService::registrar('Antivírus', 'Instalar', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function verificarAgora(): void
    {
        AuthMiddleware::checkModulo('seguranca_antivirus');
        header('Content-Type: application/json');

        set_time_limit(300);

        $caminho = trim($_POST['caminho'] ?? '') ?: null;
        $resultado = $this->service->verificarAgora($caminho, 'manual');

        AuditService::registrar('Antivírus', 'Verificar agora', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function ativarTempoReal(): void
    {
        AuthMiddleware::checkModulo('seguranca_antivirus');
        header('Content-Type: application/json');

        $resultado = $this->service->ativarTempoReal();

        AuditService::registrar('Antivírus', 'Ativar tempo real', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function desativarTempoReal(): void
    {
        AuthMiddleware::checkModulo('seguranca_antivirus');
        header('Content-Type: application/json');

        $resultado = $this->service->desativarTempoReal();

        AuditService::registrar('Antivírus', 'Desativar tempo real', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function verificacaoPeriodica(): void
    {
        AuthMiddleware::checkModulo('seguranca_antivirus');
        header('Content-Type: application/json');

        if ($this->verificacaoPeriodicaAtiva()) {
            echo json_encode(['success' => true, 'message' => 'Verificação periódica já estava ativa.']);
            return;
        }

        $resultado = (new CronService())->criar([
            'nome' => self::NOME_JOB_CRON,
            'descricao' => 'Verifica diariamente os arquivos dos compartilhamentos do Samba em busca de ameaças (Segurança > Antivírus).',
            'expressao' => '@daily',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd antivirus:verificar',
            'ativo' => true,
        ]);

        AuditService::registrar('Antivírus', 'Ativar verificação periódica', $resultado['message']);

        echo json_encode($resultado);
    }

    public function quarentenaExcluir(): void
    {
        AuthMiddleware::checkModulo('seguranca_antivirus');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluirDaQuarentena($id);

        AuditService::registrar('Antivírus', 'Excluir da quarentena', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    private function verificacaoPeriodicaAtiva(): bool
    {
        foreach ((new CronService())->listar() as $job) {
            if ($job['nome'] === self::NOME_JOB_CRON) {
                return true;
            }
        }

        return false;
    }
}
