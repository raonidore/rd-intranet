<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Services\AtualizacaoService;
use App\Services\AuditService;
use App\Services\ConfigService;
use App\Services\CronService;
use App\Services\NotificationService;
use App\Services\PassoManualService;

class AtualizacaoController extends Controller
{
    private const NOME_JOB_CRON = 'Verificar atualizações do sistema';

    private AtualizacaoService $service;
    private PassoManualService $passoManual;

    public function __construct()
    {
        $this->service = new AtualizacaoService();
        $this->passoManual = new PassoManualService();
    }

    public function index(): void
    {
        AuthMiddleware::checkAdmin();

        $this->view('administracao/atualizacoes', [
            'commitLocal' => $this->service->commitInfo('HEAD'),
            'commitRemoto' => $this->service->commitInfo('origin/main'),
            'commitsPendentes' => $this->service->commitsPendentes(),
            'verificadoEm' => ConfigService::get('atualizacao_verificado_em'),
            'ultimoErro' => ConfigService::get('atualizacao_ultimo_erro'),
            'podeReverter' => $this->service->podeReverter(),
            'historico' => $this->service->historico(),
            'checagemDiariaAtiva' => $this->checagemDiariaAtiva(),
            'passosManuais' => $this->passoManual->listar(),
        ]);
    }

    public function confirmarPassoManual(): void
    {
        AuthMiddleware::checkAdmin();

        $chave = trim($_POST['chave'] ?? '');
        $this->passoManual->confirmar($chave, $_SESSION['usuario']['id'] ?? null);

        AuditService::registrar('Atualizações', 'Confirmar passo manual', "Passo \"{$chave}\" confirmado como executado.");
        NotificationService::success('Passo marcado como executado.');

        header('Location: ' . url('/administracao/atualizacoes'));
        exit;
    }

    public function desconfirmarPassoManual(): void
    {
        AuthMiddleware::checkAdmin();

        $chave = trim($_POST['chave'] ?? '');
        $this->passoManual->desconfirmar($chave);

        AuditService::registrar('Atualizações', 'Desfazer confirmação de passo manual', "Passo \"{$chave}\" voltou a ficar pendente.");
        NotificationService::success('Confirmação desfeita.');

        header('Location: ' . url('/administracao/atualizacoes'));
        exit;
    }

    public function verificar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        echo json_encode($this->service->verificar());
    }

    public function aplicar(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        // git fetch/merge + composer + migrations pode passar do
        // max_execution_time padrao.
        set_time_limit(180);

        $resultado = $this->service->aplicar($_SESSION['usuario']['id'] ?? null);

        AuditService::registrar('Atualizações', 'Aplicar', $resultado['success']
            ? "Atualizado de {$resultado['commit_antes']} para {$resultado['commit_depois']}."
            : "Falha ao atualizar: {$resultado['message']}");

        echo json_encode($resultado);
    }

    public function reverter(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        $resultado = $this->service->reverter($_SESSION['usuario']['id'] ?? null);

        AuditService::registrar('Atualizações', 'Reverter', $resultado['success']
            ? "Revertido com sucesso: {$resultado['message']}"
            : "Falha ao reverter: {$resultado['message']}");

        echo json_encode($resultado);
    }

    public function checagemDiaria(): void
    {
        AuthMiddleware::checkAdmin();
        header('Content-Type: application/json');

        if ($this->checagemDiariaAtiva()) {
            echo json_encode(['success' => true, 'message' => 'Verificação diária já estava ativa.']);
            return;
        }

        $resultado = (new CronService())->criar([
            'nome' => self::NOME_JOB_CRON,
            'descricao' => 'Verifica diariamente se há atualizações disponíveis no repositório (Administração > Atualizações).',
            'expressao' => '@daily',
            'usuario_execucao' => 'www-data',
            'comando' => 'php /var/www/rd.intranet/rd atualizacao:verificar',
            'ativo' => true,
        ]);

        AuditService::registrar('Atualizações', 'Ativar verificação diária', $resultado['message']);

        echo json_encode($resultado);
    }

    private function checagemDiariaAtiva(): bool
    {
        foreach ((new CronService())->listar() as $job) {
            if ($job['nome'] === self::NOME_JOB_CRON) {
                return true;
            }
        }

        return false;
    }
}
