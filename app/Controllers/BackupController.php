<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\CronService;
use App\Services\EmailService;

class BackupController extends Controller
{
    private BackupService $service;

    public function __construct()
    {
        $this->service = new BackupService();
    }

    public function configuracao(): void
    {
        AuthMiddleware::checkModulo('backup_configuracao');

        $this->view('backup/configuracao', [
            'destinos' => $this->service->listarDestinos(),
            'compartilhamentosAtivos' => $this->service->compartilhamentosAtivos(),
            'jobsCron' => (new CronService())->listar(),
            'execucaoEmAndamento' => $this->service->execucaoEmAndamento(),
            'emailConfigurado' => (new EmailService())->configurado(),
        ]);
    }

    public function salvar(): void
    {
        AuthMiddleware::checkModulo('backup_configuracao');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0) ?: null;
        $resultado = $this->service->salvarDestino($_POST, $id);

        AuditService::registrar('Backup', $id ? 'Editar destino' : 'Criar destino', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function excluir(): void
    {
        AuthMiddleware::checkModulo('backup_configuracao');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->excluirDestino($id);

        AuditService::registrar('Backup', 'Excluir destino', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function ativar(): void
    {
        AuthMiddleware::checkModulo('backup_configuracao');
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->ativar($id);

        AuditService::registrar('Backup', 'Ativar destino', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function testarConexao(): void
    {
        AuthMiddleware::checkModulo('backup_configuracao');
        header('Content-Type: application/json');

        set_time_limit(45);

        echo json_encode($this->service->testarConexao($_POST));
    }

    public function agendar(): void
    {
        AuthMiddleware::checkModulo('backup_configuracao');
        header('Content-Type: application/json');

        $destinoId = (int)($_POST['destino_id'] ?? 0);
        $destino = $this->service->buscarDestino($destinoId);

        if (!$destino) {
            echo json_encode(['success' => false, 'message' => 'Destino não encontrado.']);
            return;
        }

        $expressao = trim($_POST['expressao'] ?? '') ?: '0 3 * * *';
        $nomeJob = 'Backup em nuvem - ' . $destino['nome'];
        $cron = new CronService();

        $jobExistente = null;
        foreach ($cron->listar() as $job) {
            if ($job['nome'] === $nomeJob) {
                $jobExistente = $job;
                break;
            }
        }

        $dadosJob = [
            'nome' => $nomeJob,
            'descricao' => 'Espelha os compartilhamentos do Samba para o destino de backup "' . $destino['nome'] . '" (Backup > Configuração).',
            'expressao' => $expressao,
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd backup:executar ' . $destinoId,
            'ativo' => true,
        ];

        $resultado = $jobExistente
            ? $cron->atualizar((int)$jobExistente['id'], $dadosJob)
            : $cron->criar($dadosJob);

        AuditService::registrar('Backup', 'Agendar destino', $resultado['message']);

        echo json_encode($resultado);
    }

    public function executarAgora(): void
    {
        AuthMiddleware::checkModulo('backup_configuracao');
        header('Content-Type: application/json');

        $destinoId = (int)($_POST['id'] ?? 0);
        $resultado = $this->service->executarAgora($destinoId, 'manual');

        AuditService::registrar('Backup', 'Rodar backup agora', $resultado['message'] ?? '');

        echo json_encode($resultado);
    }

    public function status(): void
    {
        AuthMiddleware::checkModulo('backup_configuracao');
        header('Content-Type: application/json');

        $execucaoId = (int)($_GET['execucao_id'] ?? 0);

        echo json_encode($this->service->statusExecucao($execucaoId));
    }

    public function historico(): void
    {
        AuthMiddleware::checkModulo('backup_historico');

        $this->view('backup/historico', [
            'execucoes' => $this->service->historico(),
        ]);
    }
}
